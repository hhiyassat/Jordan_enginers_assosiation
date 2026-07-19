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
    private array $errors = [];

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

        $validRoles = ['staff', 'auditor', 'admin'];
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
            if (in_array($field['type'] ?? '', ['select', 'radio', 'multiselect', 'checkbox_group'])) {
                if (! isset($field['options']) || ! is_array($field['options']) || count($field['options']) === 0) {
                    $this->errors[$prefix . '.options'] = "الحقل [{$i}] من نوع {$field['type']} يجب أن يحتوي على قائمة options غير فارغة.";
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

        $validTypes = ['fixed', 'tiered', 'formula'];
        $feeType = $schema['fee']['type'] ?? null;

        if ($feeType && ! in_array($feeType, $validTypes)) {
            $this->errors['schema.fee.type'] = 'نوع الرسوم غير مدعوم. القيم المدعومة: ' . implode('، ', $validTypes) . '.';
        }

        if ($feeType === 'fixed' && ! isset($schema['fee']['amount'])) {
            $this->errors['schema.fee.amount'] = 'الرسوم الثابتة (fixed) تتطلب حقل amount.';
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
