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
        $schema['workflow']['stages'][0]['role'] = 'applicant'; // not in staff/auditor/admin
        $errors = (new SchemaStructureValidator())->validate($schema);
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('schema.workflow.stages[0].role', $errors);
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
}
