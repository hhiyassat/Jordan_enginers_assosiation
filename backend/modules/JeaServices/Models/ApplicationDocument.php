<?php

namespace Modules\JeaServices\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'application_id', 'document_id', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function uploader(): BelongsTo    { return $this->belongsTo(User::class, 'uploaded_by'); }
}
