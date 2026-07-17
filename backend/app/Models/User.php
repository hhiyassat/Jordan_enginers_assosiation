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
        'annual_quota_m2',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'password_changed_at' => 'datetime',
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

    public function isAdmin(): bool     { return $this->role === 'admin'; }
    public function isStaff(): bool     { return $this->role === 'staff'; }
    public function isAuditor(): bool   { return $this->role === 'auditor'; }
    public function isApplicant(): bool { return $this->role === 'applicant'; }

    /** Staff, auditors, and admins can all review applications */
    public function isReviewer(): bool
    {
        return in_array($this->role, ['staff', 'auditor', 'admin']);
    }
}
