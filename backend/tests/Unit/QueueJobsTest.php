<?php

namespace Tests\Unit;

use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Facades\Queue;
use Integrations\Nashmi\Jobs\ProcessNashmiOutboundJob;
use Tests\TestCase;

class QueueJobsTest extends TestCase
{
    public function test_notification_job_dispatch(): void
    {
        Queue::fake();

        ProcessNotificationJob::dispatch(
            1,
            'application_status',
            'عنوان',
            'Title',
            'محتوى',
            'Body',
            '/app/1'
        );

        Queue::assertPushed(ProcessNotificationJob::class, function ($job) {
            return $job->userId === 1 && $job->type === 'application_status';
        });
    }

    public function test_nashmi_outbound_job_dispatch(): void
    {
        Queue::fake();

        ProcessNashmiOutboundJob::dispatch(10, ['summary' => 'done']);

        Queue::assertPushed(ProcessNashmiOutboundJob::class, function ($job) {
            return $job->cycleId === 10;
        });
    }
}
