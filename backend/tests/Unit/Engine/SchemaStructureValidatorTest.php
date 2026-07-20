<?php

namespace Tests\Unit\Engine;

use App\Engine\SchemaStructureValidator;
use App\Engine\StageActions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the meta-validator that ServiceCatalogController::store()/update()
 * runs before persisting a schema. The most important invariant this pins
 * is that the workflow action allowlist is StageActions::REGISTRY — not a
 * stale hand-maintained list. Previously it drifted 22 ids behind and the
 * Hukm generator marked schemas "sahih" that this endpoint then 422'd.
 */
class SchemaStructureValidatorTest extends TestCase
{
    private function baseSchema(array $overrides = []): array
    {
        return array_replace_recursive([
            'workflow' => [
                'stages' => [
                    [
                        'id'        => 'review',
                        'label_ar'  => 'مراجعة',
                        'role'      => 'staff',
                        'sla_hours' => 24,
                        'actions'   => ['approve', 'reject'],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_bare_valid_schema_passes(): void
    {
        $errors = (new SchemaStructureValidator())->validate($this->baseSchema());
        $this->assertNull($errors);
    }

    public function test_missing_workflow_stages_is_rejected(): void
    {
        $errors = (new SchemaStructureValidator())->validate([]);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages', $errors);
    }

    public function test_empty_stages_array_is_rejected(): void
    {
        $errors = (new SchemaStructureValidator())->validate(['workflow' => ['stages' => []]]);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages', $errors);
    }

    public function test_duplicate_stage_ids_are_flagged(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'][] = $schema['workflow']['stages'][0]; // duplicate
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[1].id', $errors);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $schema = $this->baseSchema();
        // 'office' is not a real role — the seeder never emits it and the
        // User model doesn't recognise it. Any other genuinely-unknown token
        // works here; we just need something outside the allowlist.
        $schema['workflow']['stages'][0]['role'] = 'office';
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[0].role', $errors);
    }

    /**
     * JORD-52 regression: the validator used to reject 'applicant' as a
     * stage role, which broke every attempt to save an edit on the 50/57
     * services whose stage[0] is an office_submission stage with
     * role='applicant' (per ServicePlan2026Seeder). Every AI-assistant
     * change on DRW-P-001, CERT-*, SRV-*, etc. was refused with
     * "role يجب أن يكون: staff, auditor, admin" even when the workflow
     * itself wasn't touched. The fix adds 'applicant' to the allowlist.
     */
    public function test_applicant_role_is_accepted_on_submission_stage(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'] = [
            [
                'id'        => 'office_submission',
                'label_ar'  => 'تقديم الطلب من المكتب الهندسي',
                'role'      => 'applicant',
                'sla_hours' => 72,
                'actions'   => ['submit'],
            ],
            [
                'id'        => 'first_review',
                'label_ar'  => 'المراجعة الأولى',
                'role'      => 'staff',
                'sla_hours' => 48,
                'actions'   => ['approve', 'reject'],
            ],
        ];
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertNull($errors,
            'applicant is a legitimate role for stage[0] submission — the seeder produces it on 50/57 services');
    }

    public function test_missing_sla_hours_is_rejected(): void
    {
        $schema = $this->baseSchema();
        unset($schema['workflow']['stages'][0]['sla_hours']);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[0].sla_hours', $errors);
    }

    /**
     * Regression: previously the allowlist was hardcoded to 3 items. The
     * Hukm generator emits richer action ids that are perfectly legal per
     * the StageActions registry (used by seeders + reviewer console). The
     * validator now consults that registry as the single source of truth.
     *
     * @return list<array{string}>
     */
    public static function registryActionIds(): array
    {
        return [
            ['approve'],
            ['reject'],
            ['request_modifications'],
            ['disburse_payment'],
            ['notify_parties'],
            ['issue_certificate'],
            ['serve_document'],
            ['schedule_inspection'],
            ['publish_posting'],
            ['override_first_auditor'],
        ];
    }

    #[DataProvider('registryActionIds')]
    public function test_action_ids_from_stage_actions_registry_are_accepted(string $actionId): void
    {
        $this->assertArrayHasKey($actionId, StageActions::REGISTRY,
            "Test premise: {$actionId} must exist in StageActions::REGISTRY. Fix the data provider if the registry was renamed.");

        $schema = $this->baseSchema();
        $schema['workflow']['stages'][0]['actions'] = [$actionId];

        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertNull($errors,
            "Action id '{$actionId}' is registered in StageActions but was rejected: "
            . json_encode($errors, JSON_UNESCAPED_UNICODE));
    }

    public function test_unregistered_action_id_is_rejected_with_useful_message(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'][0]['actions'] = ['approve', 'summon_the_kraken'];

        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[0].actions', $errors);
        $this->assertStringContainsString('summon_the_kraken', $errors['schema.workflow.stages[0].actions']);
    }

    public function test_empty_actions_array_is_rejected(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'][0]['actions'] = [];
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[0].actions', $errors);
    }

    public function test_optional_actions_can_be_omitted(): void
    {
        $schema = $this->baseSchema();
        unset($schema['workflow']['stages'][0]['actions']);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertNull($errors);
    }

    public function test_fields_of_type_select_require_options(): void
    {
        $schema = $this->baseSchema([
            'fields' => [
                ['id' => 'country', 'label_ar' => 'الدولة', 'type' => 'select'], // missing options
            ],
        ]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.fields[0].options', $errors);
    }

    public function test_fixed_fee_requires_amount(): void
    {
        $schema = $this->baseSchema(['fee' => ['type' => 'fixed']]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.fee.amount', $errors);
    }

    // ── JORD-3: conditional-field dependency validation ──────────────

    public function test_conditional_field_pointing_to_undefined_target_is_rejected(): void
    {
        $schema = $this->baseSchema(['fields' => [
            [
                'id' => 'child', 'label_ar' => 'child', 'type' => 'text', 'required' => false,
                'conditional' => ['field' => 'does_not_exist', 'value' => 'x'],
            ],
        ]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.fields[0].conditional.field', $errors);
        $this->assertStringContainsString('does_not_exist', $errors['schema.fields[0].conditional.field']);
    }

    public function test_conditional_field_can_reference_a_declared_field(): void
    {
        $schema = $this->baseSchema(['fields' => [
            ['id' => 'parent', 'label_ar' => 'parent', 'type' => 'text', 'required' => false],
            [
                'id' => 'child', 'label_ar' => 'child', 'type' => 'text', 'required' => false,
                'conditional' => ['field' => 'parent', 'value' => 'x'],
            ],
        ]]);
        $this->assertNull((new SchemaStructureValidator())->validate($schema));
    }

    public function test_self_referential_conditional_is_rejected(): void
    {
        $schema = $this->baseSchema(['fields' => [
            [
                'id' => 'self', 'label_ar' => 'self', 'type' => 'text', 'required' => false,
                'conditional' => ['field' => 'self', 'value' => 'x'],
            ],
        ]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.fields[0].conditional.field', $errors);
    }

    public function test_conditional_without_value_key_is_rejected(): void
    {
        $schema = $this->baseSchema(['fields' => [
            ['id' => 'a', 'label_ar' => 'a', 'type' => 'text', 'required' => false],
            [
                'id' => 'b', 'label_ar' => 'b', 'type' => 'text', 'required' => false,
                'conditional' => ['field' => 'a'], // no 'value'
            ],
        ]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.fields[1].conditional.value', $errors);
    }

    // ── JORD-8: SLA + fee bounds ─────────────────────────────────────

    public function test_zero_sla_hours_is_rejected(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'][0]['sla_hours'] = 0;
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.workflow.stages[0].sla_hours', $errors);
    }

    public function test_sla_hours_above_one_year_is_rejected(): void
    {
        $schema = $this->baseSchema();
        $schema['workflow']['stages'][0]['sla_hours'] = 24 * 366; // > 1 year
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.workflow.stages[0].sla_hours', $errors);
    }

    public function test_negative_fixed_fee_amount_is_rejected(): void
    {
        $schema = $this->baseSchema(['fee' => ['type' => 'fixed', 'amount' => -100]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.fee.amount', $errors);
        $this->assertStringContainsString('سالبة', $errors['schema.fee.amount']);
    }

    public function test_absurdly_large_fixed_fee_is_rejected(): void
    {
        $schema = $this->baseSchema(['fee' => ['type' => 'fixed', 'amount' => 1e12]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.fee.amount', $errors);
    }

    public function test_negative_tier_amount_is_rejected(): void
    {
        $schema = $this->baseSchema(['fee' => [
            'type' => 'tiered', 'field' => 'cat', 'default' => 100,
            'tiers' => ['a' => 50, 'b' => -20],
        ]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.fee.tiers.b', $errors);
    }

    public function test_negative_formula_base_is_rejected(): void
    {
        $schema = $this->baseSchema(['fee' => [
            'type' => 'formula', 'base' => -1, 'rate' => 10, 'field' => 'qty',
        ]]);
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertArrayHasKey('schema.fee.base', $errors);
    }
}
