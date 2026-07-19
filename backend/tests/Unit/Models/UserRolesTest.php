<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pins the two policy-boundary helpers on the User model:
 *   • canManageUsers() — who lands on the /admin/users surface.
 *   • canManageRole()  — the actor→target tier gate. Superuser reaches
 *     everyone; admin reaches applicant/staff/auditor only; nobody else
 *     can manage users.
 *   • canEditServices() — who can hit the service catalog mutations
 *     (POST/PUT /services + lock/unlock). Both admin AND superuser.
 *
 * A single misplaced role check here would silently open a privilege-
 * escalation path — cheap to test, expensive to miss.
 */
class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role): User
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'org'],
            ['name_ar' => 'org', 'name_en' => 'org', 'is_active' => true],
        );
        return User::create([
            'organization_id'     => $org->id,
            'name'                => $role, 'email' => "{$role}@t.esp",
            'password'            => Hash::make('Secret123!'),
            'role'                => $role, 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_can_manage_users_matches_the_tier_gate(): void
    {
        $this->assertTrue($this->make('superuser')->canManageUsers());
        $this->assertTrue($this->make('admin')->canManageUsers());
        $this->assertFalse($this->make('auditor')->canManageUsers());
        $this->assertFalse($this->make('staff')->canManageUsers());
        $this->assertFalse($this->make('applicant')->canManageUsers());
    }

    public function test_superuser_can_manage_every_role(): void
    {
        $su = $this->make('superuser');
        foreach (['applicant', 'staff', 'auditor', 'admin', 'superuser'] as $target) {
            $this->assertTrue($su->canManageRole($target),
                "Superuser must be allowed to manage {$target}");
        }
    }

    public function test_admin_can_manage_only_lower_tiers(): void
    {
        $admin = $this->make('admin');
        $this->assertTrue($admin->canManageRole('applicant'));
        $this->assertTrue($admin->canManageRole('staff'));
        $this->assertTrue($admin->canManageRole('auditor'));
        $this->assertFalse($admin->canManageRole('admin'),
            'Admin must not create/edit peer admins');
        $this->assertFalse($admin->canManageRole('superuser'),
            'Admin must NEVER escalate to superuser');
    }

    public function test_non_management_roles_cannot_manage_anyone(): void
    {
        foreach (['staff', 'auditor', 'applicant'] as $role) {
            $u = $this->make($role);
            foreach (['applicant', 'staff', 'auditor', 'admin', 'superuser'] as $target) {
                $this->assertFalse($u->canManageRole($target),
                    "{$role} must not be able to manage {$target}");
            }
        }
    }

    public function test_can_edit_services_is_admin_or_superuser_only(): void
    {
        $this->assertTrue($this->make('admin')->canEditServices());
        $this->assertTrue($this->make('superuser')->canEditServices());
        $this->assertFalse($this->make('auditor')->canEditServices());
        $this->assertFalse($this->make('staff')->canEditServices());
        $this->assertFalse($this->make('applicant')->canEditServices());
    }
}
