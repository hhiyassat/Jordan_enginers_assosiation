<?php

namespace Modules\JeaServices\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $application_id
 * @property int $reviewer_id
 * @property string $stage_id
 * @property string $decision
 * @property string|null $notes
 * @property array<string, mixed>|null $annotations
 * @property int $review_round
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApplicationReview extends Model
{
    protected $fillable = [
        'application_id', 'reviewer_id', 'stage_id', 'decision', 'notes', 'annotations', 'review_round',
    ];

    protected $casts = ['annotations' => 'array'];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
