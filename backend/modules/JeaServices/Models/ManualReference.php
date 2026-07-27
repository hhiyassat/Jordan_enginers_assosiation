<?php

declare(strict_types=1);

namespace Modules\JeaServices\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ManualReference — قاعدة واحدة من كتاب التعليمات الفنية 2025،
 * مربوطة بمكان تنفيذها في النظام.
 *
 * يُعرَض للمستخدمين عبر أيقونة (?) بجانب الحقل أو القاعدة المطبَّقة،
 * قابل للتعديل من قبل admin/superuser، ويسبِّب flag على dashboard عند
 * التعديل ليُذكِّر الفريق التقني بمراجعة الكود.
 *
 * @property int $id
 * @property string|null $chapter
 * @property string|null $section
 * @property int|null $page
 * @property string|null $rule_type
 * @property string $text_ar
 * @property string $text_ar_original
 * @property string|null $jord_ticket
 * @property string $target_type
 * @property string|null $target_id
 * @property string|null $target_service_code
 * @property int|null $edited_by
 * @property Carbon|null $edited_at
 * @property bool $needs_reimplementation
 * @property string|null $edit_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ManualReference extends Model
{
    protected $fillable = [
        'chapter', 'section', 'page', 'rule_type',
        'text_ar', 'text_ar_original', 'jord_ticket',
        'target_type', 'target_id', 'target_service_code',
        'edited_by', 'edited_at', 'needs_reimplementation', 'edit_reason',
    ];

    protected $casts = [
        'page' => 'integer',
        'edited_at' => 'datetime',
        'needs_reimplementation' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** Return true when the current text differs from the seed-time original. */
    public function isEdited(): bool
    {
        return $this->text_ar !== $this->text_ar_original;
    }
}
