<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * CS-02: real queue-worker integration test.
 *
 * Runs the notification job through the `database` driver so that the
 * job actually lands in the `jobs` table (which this sprint adds) and
 * is executed by the standard framework worker path. Verifies:
 *   1. The migrations shipped in this sprint create the required tables.
 *   2. Dispatch on a non-`sync` driver enqueues, not runs.
 *   3. `Queue::pop()` + `fire()` executes handle() end-to-end.
 *   4. The persisted Notification row reflects the job payload.
 *
 * We use `Queue::pop()->fire()` instead of shelling out to
 * `queue:work --once` so the assertion runs inside the same PHPUnit
 * transaction (RefreshDatabase) and cleans up between tests.
 */
class RealWorkerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_driver_persists_and_worker_processes_notification_job(): void
    {
        Config::set('queue.default', 'database');

        $this->assertTrue(
            \Schema::hasTable('jobs'),
            'CS-02 migration must create the `jobs` table'
        );
        $this->assertTrue(
            \Schema::hasTable('failed_jobs'),
            'CS-02 migration must create the `failed_jobs` table'
        );

        $org  = Organization::create(['name_ar' => 'W', 'name_en' => 'W', 'slug' => 'w-cs02']);
        $user = User::create([
            'organization_id' => $org->id,
            'name'            => 'Worker Test',
            'email'           => 'worker@example.test',
            'password'        => Hash::make('SomethingSafe-1234!'),
            'role'            => 'applicant',
            'is_active'       => true,
        ]);

        // Dispatch on the database driver — must land in jobs table.
        ProcessNotificationJob::dispatch(
            userId:        $user->id,
            type:          'security.password_changed',
            titleAr:       'اختبار',
            titleEn:       'Test',
            bodyAr:        'الجسم',
            bodyEn:        'Body',
            actionUrl:     null,
            correlationId: 'corr-worker-1',
        );

        $this->assertSame(1, DB::table('jobs')->count(),
            'Dispatch on database driver should persist a jobs row');
        $this->assertSame(0, Notification::where('user_id', $user->id)->count(),
            'Notification must not exist yet — the job has only been enqueued');

        // Pop the next job off the queue and run it end-to-end. This
        // exercises the same path that `php artisan queue:work` uses
        // internally.
        $job = Queue::connection('database')->pop();
        $this->assertNotNull($job, 'Queue::pop() should return the dispatched job');
        $job->fire();

        // The job's handle() should have called NotificationService::sendToUser
        // which inserts the notification row.
        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification, 'Worker must have created the Notification row');
        $this->assertSame('security.password_changed', $notification->type);

        $this->assertSame(0, DB::table('jobs')->count(),
            'Successful job should be removed from the queue');
        $this->assertSame(0, DB::table('failed_jobs')->count(),
            'Successful job must NOT land in failed_jobs');
    }
}
