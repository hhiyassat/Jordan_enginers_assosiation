<?php

namespace App\Engine;

use App\Models\ServiceDefinition;

/**
 * SchemaValidator — translates JSON schema constraints to Laravel validation
 *
 * SEC-006: All input validated through FormRequest or SchemaValidator.
 * WF-005: Validation failure is EDA-10 Correctable Defect — returns field errors.
 * P-1 (BUILD_CONTRACT): Validation rules are NEVER removed to unblock a flow.
 *
 * How it works:
 *   - Reads schema.fields array from the service definition
 *   - Builds Laravel-style validation rules for each field
 *   - Returns null on success, associative error array on failure
 */
class SchemaValidator
{
    public function __construct(private readonly ServiceDefinition $service) {}

    /**
     * Validate form data against the schema fields.
     *
     * Returns null if valid; returns ['field_id' => 'error message'] if invalid.
     *
     * WF-005 / EDA-10: Failure returns field-level errors, application stays in draft.
     */
    public function validateData(array $data): ?array
    {
        $errors = [];

        foreach ($this->service->getFields() as $field) {
            $fieldId = $field['id'] ?? null;
            if ($fieldId === null) {
                continue; // malformed field — skip rather than write to null key
            }
            $value = $data[$fieldId] ?? null;

            // Check conditional fields — skip if condition not met
            if (isset($field['conditional'])) {
                $condField = $field['conditional']['field'];
                $condValue = $field['conditional']['value'];
                if (($data[$condField] ?? null) !== $condValue) {
                    continue; // field not visible — skip validation
                }
            }

            // Required check
            if (($field['required'] ?? false) && $this->isEmpty($value)) {
                $errors[$fieldId] = $field['label_ar'] . ' مطلوب.';
                continue;
            }

            if ($this->isEmpty($value)) {
                continue; // optional and empty — skip further checks
            }

            // Type-specific validation
            $typeError = $this->validateType($field, $value);
            if ($typeError) {
                $errors[$fieldId] = $typeError;
                continue;
            }

            // Pattern validation
            if (isset($field['pattern'])) {
                $pattern = $field['pattern'];
                if (! preg_match('/' . str_replace('/', '\/', $pattern) . '/', (string) $value)) {
                    $errors[$fieldId] = $field['label_ar'] . ': التنسيق غير صحيح.';
                    continue;
                }
            }

            // Length constraints
            if (is_string($value)) {
                if (isset($field['min_length']) && mb_strlen($value) < (int) $field['min_length']) {
                    $errors[$fieldId] = $field['label_ar'] . ': يجب أن يكون على الأقل ' . $field['min_length'] . ' أحرف.';
                    continue;
                }
                if (isset($field['max_length']) && mb_strlen($value) > (int) $field['max_length']) {
                    $errors[$fieldId] = $field['label_ar'] . ': يجب ألا يتجاوز ' . $field['max_length'] . ' حرفاً.';
                    continue;
                }
            }

            // Numeric range
            if (is_numeric($value)) {
                if (isset($field['min']) && (float) $value < (float) $field['min']) {
                    $errors[$fieldId] = $field['label_ar'] . ': يجب أن يكون ' . $field['min'] . ' أو أكثر.';
                    continue;
                }
                if (isset($field['max']) && (float) $value > (float) $field['max']) {
                    $errors[$fieldId] = $field['label_ar'] . ': يجب أن يكون ' . $field['max'] . ' أو أقل.';
                    continue;
                }
            }

            // Options validation for single-value types
            if (in_array($field['type'], ['select', 'radio']) && isset($field['options'])) {
                $validValues = array_column($field['options'], 'value');
                if (! in_array($value, $validValues)) {
                    $errors[$fieldId] = $field['label_ar'] . ': قيمة غير مسموح بها.';
                }
            }

            // Options validation for multi-value types (multiselect / checkbox_group)
            if (in_array($field['type'], ['multiselect', 'checkbox_group']) && isset($field['options'])) {
                $validValues  = array_column($field['options'], 'value');
                $selectedVals = is_array($value) ? $value : [$value];
                $invalid      = array_diff($selectedVals, $validValues);
                if (! empty($invalid)) {
                    $errors[$fieldId] = $field['label_ar'] . ': قيم غير مسموح بها: ' . implode(', ', $invalid) . '.';
                }
            }
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Validate that required documents have been uploaded.
     *
     * Returns null if valid; returns associative error array if required docs missing.
     * Conditional documents are only required if their condition is met.
     */
    public function validateDocuments(array $uploadedDocumentIds, array $formData = []): ?array
    {
        $errors = [];

        foreach ($this->service->getDocuments() as $doc) {
            if (! ($doc['required'] ?? false)) {
                continue;
            }

            // Check conditional requirement
            if (isset($doc['conditional'])) {
                $condField = $doc['conditional']['field'];
                $condValue = $doc['conditional']['value'];
                if (($formData[$condField] ?? null) !== $condValue) {
                    continue; // doc not required for this form data
                }
            }

            if (! in_array($doc['id'], $uploadedDocumentIds)) {
                $errors[$doc['id']] = $doc['label_ar'] . ' مطلوب.';
            }
        }

        return empty($errors) ? null : $errors;
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    private function validateType(array $field, mixed $value): ?string
    {
        return match ($field['type']) {
            'number' => is_numeric($value) ? null : ($field['label_ar'] . ': يجب أن يكون رقماً.'),
            'email'  => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : ($field['label_ar'] . ': بريد إلكتروني غير صحيح.'),
            'date'   => $this->isValidDate($value) ? null : ($field['label_ar'] . ': تاريخ غير صحيح.'),
            default  => null,
        };
    }

    private function isValidDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
