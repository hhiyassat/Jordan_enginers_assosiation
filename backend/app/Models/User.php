<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User
 *
 * SEC-005: Role-based access. CheckRole middleware reads $user->role.
 * SEC-004: must_change_password + password_changed_at drive EnforcePasswordPolicy.
 * DATA-004: SoftDeletes — user records are never hard-deleted.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'email', 'password', 'role', 'phone',
        'is_active', 'must_change_password', 'password_changed_at', 'email_verified_at',
        'annual_quota_m2', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'password_changed_at' => 'datetime',
        'last_seen_at'        => 'datetime',
        'is_active'           => 'boolean',
        'must_change_password' => 'boolean',
        'annual_quota_m2'     => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // ── Role helpers (used by CheckRole middleware) ────────────────────

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isSuperuser(): bool { return $this->role === 'superuser'; }
    public function isAdmin(): bool     { return $this->role === 'admin'; }
    public function isStaff(): bool     { return $this->role === 'staff'; }
    public function isAuditor(): bool   { return $this->role === 'auditor'; }
    public function isApplicant(): bool { return $this->role === 'applicant'; }

    /** Staff, auditors, admins, and superusers can all review applications */
    public function isReviewer(): bool
    {
        return in_array($this->role, ['staff', 'auditor', 'admin', 'superuser']);
    }

    /**
     * Both superuser and admin can enter the user-management surface.
     * Superuser manages every role; admin can only touch the tiers BELOW
     * them (applicant, staff, auditor) — see canManageRole(). Superuser's
     * OWN credentials, once past the first-login gate, are CLI-only.
     */
    public function canManageUsers(): bool
    {
        return $this->isSuperuser() || $this->isAdmin();
    }

    /**
     * True when this user is allowed to create/edit/delete a target whose
     * role is `$targetRole`. Encodes the tier boundary:
     *   • superuser → any role
     *   • admin     → applicant, staff, auditor only
     *   • others    → nothing
     */
    public function canManageRole(string $targetRole): bool
    {
        if ($this->isSuperuser()) return true;
        if ($this->isAdmin()) {
            return in_array($targetRole, ['applicant', 'staff', 'auditor'], true);
        }
        return false;
    }

    /**
     * Authorized to edit service definitions and toggle their lock state.
     * Both admin and superuser qualify; every mutation is still gated by
     * ServiceDefinition::isLocked() so protection is layered.
     */
    public function canEditServices(): bool
    {
        return $this->isAdmin() || $this->isSuperuser();
    }
}
