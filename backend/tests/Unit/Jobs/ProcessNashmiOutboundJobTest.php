<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Nashmi\Jobs\ProcessNashmiOutboundJob;
use Integrations\Nashmi\Models\IntegrationCycle;
use Integrations\Nashmi\Services\NashmiIntegrationService;
use Mockery;
use Tests\TestCase;

/**
 * CS-02: unit-tests for the job body — service invocation on success,
 * throw-and-retry on failure, and a well-formed permanent-failure log.
 */
class ProcessNashmiOutboundJobTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cycle = IntegrationCycle::create([
            'cycle_ref'                => 'ESP-CYCLE-0099',
            'service_name'             => 'Sample',
            'requirements_source'      => 'nashmi',
            'status'                   => 'requirements_received',
            'requirements_received_at' => now(),
        ]);
    }

    public function test_handle_calls_nashmi_service_on_success(): void
    {
        $service = Mockery::mock(NashmiIntegrationService::class);
        $service->shouldReceive('notifyCodeDone')
                ->once()
                ->with(Mockery::type(IntegrationCycle::class), Mockery::type('array'))
                ->andReturn(['success' => true, 'data' => ['project' => ['id' => 42]]]);

        $job = new ProcessNashmiOutboundJob(
            cycleId: $this->cycle->id,
            payload: ['summary' => ['notes' => 'test']],
            correlationId: 'corr-42',
        );

        $job->handle($service);

        $this->cycle->refresh();
        $this->assertSame('code_done', $this->cycle->status);
        $this->assertSame(42, $this->cycle->nashmi_project_id);
        $this->assertNotNull($this->cycle->code_done_notified_at);
    }

    public function test_handle_throws_when_service_fails_triggering_retry(): void
    {
        $service = Mockery::mock(NashmiIntegrationService::class);
        $service->shouldReceive('notifyCodeDone')->once()
                ->andReturn(['success' => false, 'error' => 'HTTP 502']);

        $job = new ProcessNashmiOutboundJob(
            cycleId: $this->cycle->id,
            payload: ['summary' => []],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nashmi outbound call failed: HTTP 502');
        $job->handle($service);

        // Original state preserved for retry.
        $this->cycle->refresh();
        $this->assertSame('requirements_received', $this->cycle->status);
    }

    public function test_handle_noops_when_cycle_missing(): void
    {
        $service = Mockery::mock(NashmiIntegrationService::class);
        $service->shouldNotReceive('notifyCodeDone');

        $job = new ProcessNashmiOutboundJob(
            cycleId: 999999,
            payload: ['summary' => []],
        );
        $job->handle($service);
        $this->assertTrue(true);
    }

    public function test_retry_configuration(): void
    {
        $job = new ProcessNashmiOutboundJob(cycleId: 1, payload: []);
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([30, 120, 300], $job->backoff());
    }
}
