<?php

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

        foreach ($schema['fields'] as $i => $field) {
            $prefix = "schema.fields[$i]";

            if (empty($field['id'])) {
                $this->errors[$prefix . '.id'] = "الحقل [{$i}] يجب أن يحتوي على id.";
            } else {
                if (in_array($field['id'], $fieldIds)) {
                    $this->errors[$prefix . '.id'] = "معرف الحقل '{$field['id']}' مكرر.";
                }
                $fieldIds[] = $field['id'];
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
    }
}
