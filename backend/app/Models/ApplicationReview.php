<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationReview extends Model
{
    protected $fillable = [
        'application_id', 'reviewer_id', 'stage_id', 'decision', 'notes', 'annotations', 'review_round',
    ];

    protected $casts = ['annotations' => 'array'];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function reviewer(): BelongsTo    { return $this->belongsTo(User::class, 'reviewer_id'); }
}
