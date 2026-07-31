<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * M-09 · `notifications:prune` command.
 *
 * Deletes read notifications past the read retention window and
 * unread notifications past the (longer) unread retention window.
 */
class NotificationsPruneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'np', 'is_active' => true]);
        $this->user = User::create([
            'organization_id' => $org->id, 'name' => 'u', 'email' => 'np@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_prune_deletes_read_notifications_past_the_window(): void
    {
        config(['esp.notification_retention_days' => 30, 'esp.notification_unread_retention_days' => 365]);
        $this->makeNotification('old-read', read_at: now()->subDays(60), created_at: now()->subDays(60));
        $this->makeNotification('fresh-read', read_at: now()->subDays(5), created_at: now()->subDays(5));
        $this->makeNotification('unread-fresh', read_at: null, created_at: now()->subDays(60));

        $this->artisan('notifications:prune')->assertSuccessful();

        $ids = Notification::withoutOrgScope()->pluck('title')->all();
        $this->assertNotContains('old-read', $ids);
        $this->assertContains('fresh-read', $ids);
        $this->assertContains('unread-fresh', $ids, 'Unread notifications keep the longer window.');
    }

    public function test_prune_deletes_unread_notifications_past_the_longer_window(): void
    {
        config(['esp.notification_retention_days' => 30, 'esp.notification_unread_retention_days' => 90]);
        $this->makeNotification('unread-ancient', read_at: null, created_at: now()->subDays(200));
        $this->makeNotification('unread-in-window', read_at: null, created_at: now()->subDays(30));

        $this->artisan('notifications:prune')->assertSuccessful();

        $titles = Notification::withoutOrgScope()->pluck('title')->all();
        $this->assertNotContains('unread-ancient', $titles);
        $this->assertContains('unread-in-window', $titles);
    }

    public function test_prune_dry_run_leaves_all_rows(): void
    {
        config(['esp.notification_retention_days' => 30, 'esp.notification_unread_retention_days' => 365]);
        $this->makeNotification('old-read-2', read_at: now()->subDays(90), created_at: now()->subDays(90));
        $before = Notification::withoutOrgScope()->count();

        $this->artisan('notifications:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, Notification::withoutOrgScope()->count());
    }

    private function makeNotification(string $title, ?\Illuminate\Support\Carbon $read_at, \Illuminate\Support\Carbon $created_at): Notification
    {
        $n = Notification::create([
            'organization_id' => $this->user->organization_id,
            'user_id'         => $this->user->id,
            'type'            => 'test',
            'title'           => $title,
            'body'            => 'b',
            'read_at'         => $read_at,
        ]);
        // Backfill created_at manually so retention windows can be exercised.
        $n->created_at = $created_at;
        $n->save();
        return $n;
    }
}
