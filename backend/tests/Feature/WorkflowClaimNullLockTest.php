<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Engine\WorkflowEngine;
use App\Models\Application;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * JORD-6: WorkflowEngine::claim() must not 500 when the row was deleted
 * between the caller loading the application and the lockForUpdate()
 * transaction acquiring its row. Previously the next line dereferenced
 * $locked->status and crashed with "Attempt to read property status on
 * null". The guarded path returns a clean 409 with an Arabic message.
 */
class WorkflowClaimNullLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_on_a_soft_deleted_application_returns_409_not_500(): void
    {
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => Hash::make('x'), 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code' => 'X', 'name_ar' => 'x', 'name_en' => 'x', 'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 'review', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
            ]]],
        ]);
        $app = Application::create([
            'reference_number' => 'A-DEL', 'organization_id' => $org->id,
            'service_definition_id' => $service->id, 'applicant_id' => $applicant->id,
            'status' => Application::STATUS_SUBMITTED,
            'current_stage' => 'review', 'data' => [],
            'fee_amount' => 0, 'payment_status' => 'waived',
        ]);
        $auditor = User::create([
            'organization_id' => $org->id, 'name' => 'aud', 'email' => 'aud@t.esp',
            'password' => Hash::make('x'), 'role' => 'auditor', 'is_active' => true,
            'password_changed_at' => now(),
        ]);

        // Reproduce the race: the caller holds $app, but the row was
        // soft-deleted between load and lockForUpdate().
        $app->delete();

        $engine = new WorkflowEngine($service);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $engine->claim($app, $auditor);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode(),
                'Deleted-row claim must be a 409, not a 500 from null dereference');
            $this->assertStringContainsString('الطلب', $e->getMessage());
            throw $e;
        }
    }
}
