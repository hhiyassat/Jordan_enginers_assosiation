<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Integrations\Nashmi\Jobs\ProcessNashmiOutboundJob;
use Integrations\Nashmi\Models\IntegrationCycle;
use Tests\TestCase;

/**
 * CS-02: verify that IntegrationController::notifyCodeDone dispatches
 * the ProcessNashmiOutboundJob (rather than running the HTTP call
 * synchronously) and enforces per-cycle idempotency on repeated
 * requests.
 */
class NashmiJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nashmi.integration_key'        => 'test-key',
            'nashmi.signing_secret'         => '',      // sign-off in non-prod
            'nashmi.allowed_ips'            => [],
            'nashmi.replay_window_seconds'  => 300,
            'nashmi.nonce_ttl_seconds'      => 600,
        ]);
    }

    private function payload(): array
    {
        return [
            'git_branch'    => 'main',
            'git_commit'    => 'abc123',
            'files_changed' => ['a.php'],
            'api_endpoints' => ['GET /x'],
            'notes'         => 'test',
        ];
    }

    public function test_notify_code_done_dispatches_job_and_returns_202(): void
    {
        Bus::fake();
        $cycle = IntegrationCycle::create([
            'cycle_ref'                => 'ESP-CYCLE-0001',
            'service_name'             => 'Sample',
            'requirements_source'      => 'nashmi',
            'status'                   => 'requirements_received',
            'requirements_received_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
            'X-Request-Id'      => 'req-xyz-1',
        ])->postJson("/api/integration/cycles/{$cycle->id}/notify-done", $this->payload());

        $response->assertStatus(202)
                 ->assertJson([
                     'accepted'  => true,
                     'cycle_ref' => 'ESP-CYCLE-0001',
                 ]);

        Bus::assertDispatched(ProcessNashmiOutboundJob::class, function ($job) use ($cycle) {
            return $job->cycleId === $cycle->id
                && ! empty($job->payload['summary'])
                && $job->correlationId === 'req-xyz-1';
        });
    }

    public function test_second_notify_call_is_idempotent(): void
    {
        Bus::fake();
        $cycle = IntegrationCycle::create([
            'cycle_ref'                => 'ESP-CYCLE-0002',
            'service_name'             => 'Sample',
            'requirements_source'      => 'nashmi',
            'status'                   => 'code_done',
            'code_done_notified_at'    => now(),
            'requirements_received_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
        ])->postJson("/api/integration/cycles/{$cycle->id}/notify-done", $this->payload());

        $response->assertStatus(200)
                 ->assertJson(['idempotent' => true]);

        Bus::assertNotDispatched(ProcessNashmiOutboundJob::class);
    }

    public function test_notify_on_illegal_transition_returns_422_and_dispatches_nothing(): void
    {
        Bus::fake();
        $cycle = IntegrationCycle::create([
            'cycle_ref'                => 'ESP-CYCLE-0003',
            'service_name'             => 'Sample',
            'requirements_source'      => 'nashmi',
            'status'                   => 'closed',           // terminal — no transitions allowed
            'requirements_received_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'test-key',
        ])->postJson("/api/integration/cycles/{$cycle->id}/notify-done", $this->payload());

        $response->assertStatus(422);
        Bus::assertNotDispatched(ProcessNashmiOutboundJob::class);
    }
}
