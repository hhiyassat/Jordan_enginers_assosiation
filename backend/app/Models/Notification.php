<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * JORD-9: per-user notification row.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string $body
 * @property string|null $link
 * @property string|null $related_type
 * @property int|null $related_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @method static Builder<static> forUser(User $user)
 * @method static Builder<static> unread()
 */
class Notification extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'type', 'title', 'body',
        'link', 'related_type', 'related_id', 'payload', 'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Notification> $q
     * @return Builder<Notification>
     */
    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where('user_id', $user->id);
    }

    /**
     * @param  Builder<Notification> $q
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }
}
