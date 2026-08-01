<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * C-01 · Superuser is user-management only.
 *
 * Pins the invariant: a superuser session must be rejected (403) by
 * every JEA-business admin route (dashboard, applications listing,
 * audit logs, service catalog, service fees, service lock/unlock,
 * manual references, office-registration decisions, project admin,
 * discipline admin, dues admin, AI schema generation, reviewer
 * surface), while retaining full access to the user-management
 * surface.
 *
 * If any of these assertions regress, superuser has been granted
 * cross-domain "god-mode" access — the exact defect this test
 * exists to prevent (see architecture-review C-01 / F-ADM-01).
 */
class SuperuserScopeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $superuser;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $this->superuser = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'super',
            'email'               => 'super@t.esp',
            'password'            => Hash::make('Secret123!'),
            'role'                => 'superuser',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
        $this->admin = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'admin',
            'email'               => 'admin@t.esp',
            'password'            => Hash::make('Secret123!'),
            'role'                => 'admin',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
    }

    // ── User-management surface (positive: superuser MUST keep access) ──

    public function test_superuser_can_list_users(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_superuser_can_create_admin_user(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->postJson('/api/v1/admin/users', [
            'name'     => 'new admin',
            'email'    => 'new-admin@t.esp',
            'password' => 'Aa123456!Bcd',
            'role'     => 'admin',
        ])->assertCreated();
    }

    // ── JEA-business admin surface (negative: superuser MUST be denied) ──

    #[DataProvider('deniedAdminEndpointProvider')]
    public function test_superuser_is_denied_on_jea_business_admin_endpoints(
        string $method,
        string $path,
        int $expectedStatus = 403,
    ): void {
        Sanctum::actingAs($this->superuser);
        $this->{"{$method}Json"}("/api/v1/{$path}")
            ->assertStatus($expectedStatus);
    }

    public static function deniedAdminEndpointProvider(): array
    {
        // Every entry is a JEA-business admin endpoint that superuser
        // must be denied (per the user-management-only scope policy).
        // Format: [method, path] — the middleware runs before the
        // controller, so a nonexistent numeric ID is fine.
        return [
            // Platform admin dashboard (route group at api.php:95)
            'admin dashboard'            => ['get',   'admin/dashboard'],
            'admin applications'         => ['get',   'admin/applications'],
            'admin audit logs'           => ['get',   'admin/audit-logs'],

            // JEA services admin (module routes.php:118)
            'admin services index'       => ['get',   'admin/services'],
            'admin services show'        => ['get',   'admin/services/1'],
            'admin services create'      => ['post',  'services'],
            'admin services update'      => ['put',   'services/1'],
            'admin services status'      => ['patch', 'services/1/status'],
            'admin service fees index'   => ['get',   'admin/service-fees'],
            'admin service fee update'   => ['patch', 'admin/services/1/fee'],
            'admin service lock'         => ['post',  'admin/services/1/lock'],
            'admin service unlock'       => ['post',  'admin/services/1/unlock'],
            'admin manual reference'     => ['patch', 'admin/manual-references/1'],
            'admin manual reference ack' => ['post',  'admin/manual-references/1/ack'],
            'admin office reg index'     => ['get',   'admin/office-registrations'],
            'admin office reg show'      => ['get',   'admin/office-registrations/1'],
            'admin office reg approve'   => ['post',  'admin/office-registrations/1/approve'],
            'admin office reg reject'    => ['post',  'admin/office-registrations/1/reject'],

            // JEA projects admin (module routes.php:53)
            'admin offices index'        => ['get',   'admin/offices'],
            'admin office show'          => ['get',   'admin/offices/1'],
            'admin office update'        => ['patch', 'admin/offices/1'],

            // JEA discipline admin (module routes.php:45)
            'admin complaints index'     => ['get',   'admin/complaints'],
            'admin complaint decide'     => ['post',  'admin/complaints/1/decide'],
            'admin legal fines index'    => ['get',   'admin/legal-fines'],
            'admin legal fine store'     => ['post',  'admin/legal-fines'],
            'admin legal fine pay'       => ['post',  'admin/legal-fines/1/pay'],
            'admin transfers index'      => ['get',   'admin/supervision-transfers'],
            'admin transfer assign'      => ['post',  'admin/supervision-transfers/1/assign'],

            // JEA dues admin (module routes.php:36)
            'admin dues index'           => ['get',   'admin/offices/1/dues'],
            'admin dues seed'            => ['post',  'admin/offices/1/dues/register'],
            'admin dues pay'             => ['post',  'admin/dues/1/pay'],

            // AI schema plugin admin (plugin routes.php:24)
            'admin schema generate'      => ['post',  'admin/services/generate-schema'],
            'admin schema chat'          => ['post',  'admin/services/chat-schema'],
        ];
    }

    // ── Reviewer surface (superuser is not a reviewer) ──

    public function test_superuser_is_not_a_reviewer_in_model_helpers(): void
    {
        $this->assertFalse($this->superuser->isReviewer(),
            'C-01: superuser must not be a reviewer — that role is business, not user-management.');
        $this->assertFalse($this->superuser->canEditServices(),
            'C-01: superuser must not edit service definitions.');
    }
}
