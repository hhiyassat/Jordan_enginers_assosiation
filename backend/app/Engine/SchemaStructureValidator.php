<?php

declare(strict_types=1);

namespace App\Engine;

use App\Engine\StageActions;

/**
 * SchemaStructureValidator — validates that a saved JSON schema conforms to the
 * ESP v2 schema contract before it is persisted as a ServiceDefinition.
 *
 * This is the "meta-validation" layer: it ensures every service stored in the DB
 * is structurally sound so that SchemaValidator, WorkflowEngine, and FeeCalculator
 * can run against it without silent failures.
 *
 * Called from ServiceCatalogController::store() and ::update() before saving.
 *
 * Returns null on success, or an associative ['field' => 'message'] error map on failure.
 */
class SchemaStructureValidator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param  array<string, mixed> $schema
     * @return array<string, string>|null
     */
    public function validate(array $schema): ?array
    {
        $this->errors = [];

        $this->checkWorkflow($schema);
        $this->checkFields($schema);
        $this->checkDocuments($schema);
        $this->checkFee($schema);

        return empty($this->errors) ? null : $this->errors;
    }

    // ── Workflow ─────────────────────────────────────────────────────────

    private function checkWorkflow(array $schema): void
    {
        if (! isset($schema['workflow']['stages']) || ! is_array($schema['workflow']['stages'])) {
            $this->errors['schema.workflow.stages'] = 'المخطط يجب أن يحتوي على workflow.stages كمصفوفة.';
            return;
        }

        if (count($schema['workflow']['stages']) === 0) {
            $this->errors['schema.workflow.stages'] = 'يجب تعريف مرحلة مراجعة واحدة على الأقل في workflow.stages.';
            return;
        }

        // 'applicant' is the correct role for the submission stage (stage[0]
        // on 50 of the 57 seeded services follows this pattern:
        // office_submission → first_review → auditor_review → …). Without it
        // in this allowlist, any admin edit of a submission-first schema is
        // rejected with "role must be staff/auditor/admin" even when the
        // stages themselves are untouched (JORD-52). The engine's
        // WorkflowEngine::claim() only enforces role on stages a reviewer
        // *claims*; applicants never claim their own submission stage, so
        // allowing 'applicant' here does not open a review path to them.
        $validRoles = ['applicant', 'staff', 'auditor', 'admin'];
        // Actions must be ids the platform knows how to render + dispatch.
        // StageActions::REGISTRY is the single source of truth — reviewer
        // console, seeders, and the reviewer decide endpoint all consult it.
        // Keeping this list in sync manually caused a mismatch where the
        // Hukm generator marked schemas "sahih" that this endpoint then
        // 422'd because the allowlist was 3 items behind.
        $validActions = array_keys(StageActions::REGISTRY);
        $stageIds = [];

        foreach ($schema['workflow']['stages'] as $i => $stage) {
            $prefix = "schema.workflow.stages[$i]";

            if (empty($stage['id'])) {
                $this->errors[$prefix . '.id'] = "المرحلة [{$i}] يجب أن تحتوي على حقل id.";
            } else {
                if (in_array($stage['id'], $stageIds)) {
                    $this->errors[$prefix . '.id'] = "معرف المرحلة '{$stage['id']}' مكرر.";
                }
                $stageIds[] = $stage['id'];
            }

            if (empty($stage['label_ar'])) {
                $this->errors[$prefix . '.label_ar'] = "المرحلة [{$i}] يجب أن تحتوي على label_ar.";
            }

            if (empty($stage['role']) || ! in_array($stage['role'], $validRoles)) {
                $this->errors[$prefix . '.role'] = "المرحلة [{$i}]: role يجب أن يكون: " . implode('، ', $validRoles) . '.';
            }

            if (! isset($stage['sla_hours']) || ! is_numeric($stage['sla_hours'])) {
                $this->errors[$prefix . '.sla_hours'] = "المرحلة [{$i}]: sla_hours مطلوب ويجب أن يكون رقماً.";
            } else {
                // JORD-8: bound sla_hours so a schema-authoring slip can't
                // schedule a stage for 10 years, or worse, produce a
                // negative deadline. Upper bound = 1 year in hours; lower
                // bound = 1 hour so 0/negative can't turn into "expired
                // the moment it was created".
                $sla = (float) $stage['sla_hours'];
                if ($sla < 1) {
                    $this->errors[$prefix . '.sla_hours'] = "المرحلة [{$i}]: sla_hours يجب أن يكون ساعة واحدة على الأقل.";
                } elseif ($sla > 24 * 365) {
                    $this->errors[$prefix . '.sla_hours'] = "المرحلة [{$i}]: sla_hours لا يمكن أن يتجاوز " . (24 * 365) . " ساعة (سنة واحدة).";
                }
            }

            // actions is optional but if present must be non-empty array of valid actions
            if (isset($stage['actions'])) {
                if (! is_array($stage['actions']) || count($stage['actions']) === 0) {
                    $this->errors[$prefix . '.actions'] = "المرحلة [{$i}]: actions يجب أن تكون مصفوفة غير فارغة.";
                } else {
                    $invalid = array_diff($stage['actions'], $validActions);
                    if (! empty($invalid)) {
                        $this->errors[$prefix . '.actions'] = "المرحلة [{$i}]: قيم actions غير مسموح بها: " . implode('، ', $invalid)
                            . '. القيم المسموح بها: ' . implode('، ', $validActions) . '.';
                    }
                }
            }
        }
    }

    // ── Fields ───────────────────────────────────────────────────────────

    private function checkFields(array $schema): void
    {
        if (! isset($schema['fields'])) {
            // fields is optional — a service might be document-only
            return;
        }

        if (! is_array($schema['fields'])) {
            $this->errors['schema.fields'] = 'schema.fields يجب أن تكون مصفوفة.';
            return;
        }

        $validTypes = ['text', 'textarea', 'select', 'radio', 'multiselect', 'checkbox_group', 'number', 'date', 'email'];
        $fieldIds = [];

        // First pass: collect every declared field id so the conditional
        // check below can verify the target-field reference.
        foreach ($schema['fields'] as $field) {
            if (isset($field['id']) && is_string($field['id'])) {
                $fieldIds[] = $field['id'];
            }
        }

        $seenIds = [];
        foreach ($schema['fields'] as $i => $field) {
            $prefix = "schema.fields[$i]";

            if (empty($field['id'])) {
                $this->errors[$prefix . '.id'] = "الحقل [{$i}] يجب أن يحتوي على id.";
            } else {
                if (in_array($field['id'], $seenIds)) {
                    $this->errors[$prefix . '.id'] = "معرف الحقل '{$field['id']}' مكرر.";
                }
                $seenIds[] = $field['id'];
            }

            if (empty($field['label_ar'])) {
                $this->errors[$prefix . '.label_ar'] = "الحقل [{$i}] يجب أن يحتوي على label_ar.";
            }

            if (empty($field['type']) || ! in_array($field['type'], $validTypes)) {
                $this->errors[$prefix . '.type'] = "الحقل [{$i}]: type غير مدعوم. القيم المدعومة: " . implode('، ', $validTypes) . '.';
            }

            // select/radio/multiselect/checkbox_group must have options
            // OR an options_endpoint that the frontend fetches at render
            // time (JORD-69). Either satisfies the "there must be
            // choices" contract; both empty simultaneously is invalid.
            if (in_array($field['type'] ?? '', ['select', 'radio', 'multiselect', 'checkbox_group'])) {
                $hasStaticOptions   = isset($field['options']) && is_array($field['options']) && count($field['options']) > 0;
                $hasDynamicEndpoint = !empty($field['options_endpoint']) && is_string($field['options_endpoint']);
                if (!$hasStaticOptions && !$hasDynamicEndpoint) {
                    $this->errors[$prefix . '.options'] = "الحقل [{$i}] من نوع {$field['type']} يجب أن يحتوي على قائمة options أو options_endpoint.";
                }
            }

            // JORD-3: conditional-field dependency validation.
            // A conditional block says "only show this field when field X
            // has value Y". Two failure modes were slipping through:
            //   • The referenced field X wasn't declared anywhere — the
            //     conditional then hung on nothing and the field was
            //     effectively always hidden.
            //   • Self-reference (field.conditional.field === field.id)
            //     causes an infinite hide-cycle in the applicant form.
            if (isset($field['conditional']) && is_array($field['conditional'])) {
                $target = $field['conditional']['field'] ?? null;
                if (! is_string($target) || $target === '') {
                    $this->errors[$prefix . '.conditional.field'] =
                        "الحقل [{$i}]: conditional.field يجب أن يكون معرّف حقل نصياً.";
                } elseif (! in_array($target, $fieldIds, true)) {
                    $this->errors[$prefix . '.conditional.field'] =
                        "الحقل [{$i}]: conditional.field يشير إلى '{$target}' وهو غير معرّف في المخطط.";
                } elseif (isset($field['id']) && $target === $field['id']) {
                    $this->errors[$prefix . '.conditional.field'] =
                        "الحقل [{$i}]: لا يمكن للحقل أن يعتمد على نفسه.";
                }
                if (! array_key_exists('value', $field['conditional'])) {
                    $this->errors[$prefix . '.conditional.value'] =
                        "الحقل [{$i}]: conditional.value مطلوب.";
                }
            }
        }
    }

    // ── Documents ────────────────────────────────────────────────────────

    private function checkDocuments(array $schema): void
    {
        if (! isset($schema['documents'])) {
            return; // documents is optional
        }

        if (! is_array($schema['documents'])) {
            $this->errors['schema.documents'] = 'schema.documents يجب أن تكون مصفوفة.';
            return;
        }

        $docIds = [];

        foreach ($schema['documents'] as $i => $doc) {
            $prefix = "schema.documents[$i]";

            if (empty($doc['id'])) {
                $this->errors[$prefix . '.id'] = "المستند [{$i}] يجب أن يحتوي على id.";
            } else {
                if (in_array($doc['id'], $docIds)) {
                    $this->errors[$prefix . '.id'] = "معرف المستند '{$doc['id']}' مكرر.";
                }
                $docIds[] = $doc['id'];
            }

            if (empty($doc['label_ar'])) {
                $this->errors[$prefix . '.label_ar'] = "المستند [{$i}] يجب أن يحتوي على label_ar.";
            }
        }
    }

    // ── Fee ──────────────────────────────────────────────────────────────

    private function checkFee(array $schema): void
    {
        if (! isset($schema['fee'])) {
            return; // no fee is valid
        }

        if (! is_array($schema['fee'])) {
            $this->errors['schema.fee'] = 'schema.fee يجب أن يكون كائناً.';
            return;
        }

        $validTypes = ['fixed', 'tiered', 'formula', 'matrix', 'per_unit'];
        $feeType = $schema['fee']['type'] ?? null;

        if ($feeType && ! in_array($feeType, $validTypes)) {
            $this->errors['schema.fee.type'] = 'نوع الرسوم غير مدعوم. القيم المدعومة: ' . implode('، ', $validTypes) . '.';
        }

        if ($feeType === 'fixed' && ! isset($schema['fee']['amount'])) {
            $this->errors['schema.fee.amount'] = 'الرسوم الثابتة (fixed) تتطلب حقل amount.';
        }

        // JORD-63: matrix — validate the four required shape fields so a
        // typo doesn't silently collapse every lookup to the default.
        if ($feeType === 'matrix') {
            if (!isset($schema['fee']['keys']) || !is_array($schema['fee']['keys']) || $schema['fee']['keys'] === []) {
                $this->errors['schema.fee.keys'] = 'مصفوفة الرسوم (matrix) تتطلب قائمة keys غير فارغة.';
            } else {
                // Every key must be a form field id declared elsewhere in
                // the schema — otherwise the applicant has no way to fill
                // the value and the matrix collapses to default.
                $fieldIds = [];
                if (isset($schema['fields']) && is_array($schema['fields'])) {
                    foreach ($schema['fields'] as $f) {
                        if (isset($f['id']) && is_string($f['id'])) $fieldIds[] = $f['id'];
                    }
                }
                foreach ($schema['fee']['keys'] as $i => $k) {
                    if (!is_string($k) || $k === '') {
                        $this->errors["schema.fee.keys[$i]"] = 'كل عنصر في keys يجب أن يكون معرف حقل نصياً.';
                    } elseif (!in_array($k, $fieldIds, true)) {
                        $this->errors["schema.fee.keys[$i]"] = "keys[$i]='{$k}' يشير إلى حقل غير معرف في fields.";
                    }
                }
            }
            if (!isset($schema['fee']['rates']) || !is_array($schema['fee']['rates']) || $schema['fee']['rates'] === []) {
                $this->errors['schema.fee.rates'] = 'مصفوفة الرسوم (matrix) تتطلب جدول rates غير فارغ.';
            }
            if (isset($schema['fee']['basis']) && !is_string($schema['fee']['basis'])) {
                $this->errors['schema.fee.basis'] = 'حقل basis يجب أن يكون معرف حقل نصياً.';
            }
        }

        // JORD-65: surcharges are optional but if present must be an
        // array of well-formed entries. Malformed surcharge shapes
        // silently omit the line item — worse UX than a hard 422.
        if (isset($schema['fee']['surcharges'])) {
            if (!is_array($schema['fee']['surcharges'])) {
                $this->errors['schema.fee.surcharges'] = 'حقل surcharges يجب أن يكون قائمة.';
            } else {
                $fieldIds = [];
                if (isset($schema['fields']) && is_array($schema['fields'])) {
                    foreach ($schema['fields'] as $f) {
                        if (isset($f['id']) && is_string($f['id'])) $fieldIds[] = $f['id'];
                    }
                }
                foreach ($schema['fee']['surcharges'] as $i => $s) {
                    $prefix = "schema.fee.surcharges[$i]";
                    if (!is_array($s)) {
                        $this->errors[$prefix] = "surcharges[$i] يجب أن يكون كائناً.";
                        continue;
                    }
                    $kind = $s['kind'] ?? null;
                    if (!in_array($kind, ['percent_of_base', 'per_unit'], true)) {
                        $this->errors[$prefix . '.kind'] = "surcharges[$i].kind يجب أن يكون percent_of_base أو per_unit.";
                    }
                    if (!isset($s['rate']) || !is_numeric($s['rate'])) {
                        $this->errors[$prefix . '.rate'] = "surcharges[$i].rate رقم مطلوب.";
                    }
                    if ($kind === 'per_unit') {
                        if (empty($s['basis']) || !is_string($s['basis'])) {
                            $this->errors[$prefix . '.basis'] = "surcharges[$i].basis معرف حقل نصي مطلوب لسورشارج per_unit.";
                        } elseif (!in_array($s['basis'], $fieldIds, true)) {
                            $this->errors[$prefix . '.basis'] = "surcharges[$i].basis='{$s['basis']}' يشير إلى حقل غير معرف في fields.";
                        }
                    }
                }
            }
        }

        // JORD-64: per_unit — must declare basis (form field) + rate.
        // Missing either produces silent zero fees, so require both.
        if ($feeType === 'per_unit') {
            if (empty($schema['fee']['basis']) || !is_string($schema['fee']['basis'])) {
                $this->errors['schema.fee.basis'] = 'رسوم per_unit تتطلب basis كمعرف حقل نصي.';
            } else {
                // basis must reference a real form field.
                $fieldIds = [];
                if (isset($schema['fields']) && is_array($schema['fields'])) {
                    foreach ($schema['fields'] as $f) {
                        if (isset($f['id']) && is_string($f['id'])) $fieldIds[] = $f['id'];
                    }
                }
                if (!in_array($schema['fee']['basis'], $fieldIds, true)) {
                    $this->errors['schema.fee.basis'] = "basis='{$schema['fee']['basis']}' يشير إلى حقل غير معرف في fields.";
                }
            }
            if (!isset($schema['fee']['rate']) || !is_numeric($schema['fee']['rate'])) {
                $this->errors['schema.fee.rate'] = 'رسوم per_unit تتطلب rate رقمياً.';
            }
        }

        // JORD-8: fee-amount sanity checks. A schema slip putting the
        // amount at -100 or 1e12 used to sail straight through and
        // produce refund-shaped or catastrophically-large bills.
        // Cap = 10,000,000 covers every realistic engineering-service
        // fee with room for currency inflation. Lower bound = 0
        // (freebies allowed; negatives not).
        $maxFeeAmount = 10_000_000;
        if ($feeType === 'fixed' && isset($schema['fee']['amount']) && is_numeric($schema['fee']['amount'])) {
            $amount = (float) $schema['fee']['amount'];
            if ($amount < 0) {
                $this->errors['schema.fee.amount'] = 'قيمة الرسوم لا يمكن أن تكون سالبة.';
            } elseif ($amount > $maxFeeAmount) {
                $this->errors['schema.fee.amount'] = "قيمة الرسوم لا يمكن أن تتجاوز {$maxFeeAmount}.";
            }
        }

        // Same bounds on tiered / formula: tiers[] values and base/rate
        // must be non-negative and inside the same cap.
        if ($feeType === 'tiered' && isset($schema['fee']['tiers']) && is_array($schema['fee']['tiers'])) {
            foreach ($schema['fee']['tiers'] as $tierKey => $tierAmount) {
                if (is_numeric($tierAmount)) {
                    $a = (float) $tierAmount;
                    if ($a < 0 || $a > $maxFeeAmount) {
                        $this->errors["schema.fee.tiers.{$tierKey}"] =
                            "قيمة الشريحة '{$tierKey}' خارج النطاق المسموح (0 إلى {$maxFeeAmount}).";
                    }
                }
            }
        }
        if ($feeType === 'formula') {
            foreach (['base', 'rate'] as $comp) {
                if (isset($schema['fee'][$comp]) && is_numeric($schema['fee'][$comp])) {
                    $v = (float) $schema['fee'][$comp];
                    if ($v < 0 || $v > $maxFeeAmount) {
                        $this->errors["schema.fee.{$comp}"] =
                            "قيمة {$comp} خارج النطاق المسموح (0 إلى {$maxFeeAmount}).";
                    }
                }
            }
        }
    }
}
