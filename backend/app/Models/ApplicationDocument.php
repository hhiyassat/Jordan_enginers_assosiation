<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'application_id', 'document_id', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function uploader(): BelongsTo    { return $this->belongsTo(User::class, 'uploaded_by'); }
}
