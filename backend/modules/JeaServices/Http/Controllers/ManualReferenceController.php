<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\JeaServices\Http\Requests\UpdateManualReferenceRequest;
use Modules\JeaServices\Models\ManualReference;

/**
 * ManualReferenceController — الأساس النظامي من الكتاب.
 *
 * Public read: any authenticated user (used by the (?) popover on form
 * fields to display the manual basis for a rule).
 *
 * Admin write: only admin/superuser (used during the NGO review round
 * to correct wording or align with the current interpretation).
 *
 * Every write raises `needs_reimplementation=true` and logs to
 * AuditLog so the technical team can review whether the code
 * behavior needs to change to match the new text.
 */
class ManualReferenceController extends Controller
{
    /** Read one reference — any authenticated user. */
    public function show(int $id): JsonResponse
    {
        $ref = ManualReference::findOrFail($id);

        return response()->json(['reference' => $ref]);
    }

    /**
     * List references attached to a given service (via ?service=CODE)
     * OR list all pending-edit references (via ?pending=1, admin only).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('pending')) {
            if (! $request->user()->isAdmin() && ! $request->user()->isSuperuser()) {
                abort(403);
            }
            $refs = ManualReference::where('needs_reimplementation', true)
                ->orderByDesc('edited_at')
                ->get();

            return response()->json(['references' => $refs]);
        }

        $q = ManualReference::query();
        if ($request->filled('service')) {
            $q->where('target_service_code', $request->string('service'));
        }

        return response()->json(['references' => $q->orderBy('page')->get()]);
    }

    /** Admin edits the rule text — raises pending flag. */
    public function update(UpdateManualReferenceRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $ref = ManualReference::findOrFail($id);

        // Skip write if unchanged — avoids raising the pending flag for no reason.
        if (trim($data['text_ar']) === trim($ref->text_ar)) {
            return response()->json(['reference' => $ref, 'unchanged' => true]);
        }

        $ref->update([
            'text_ar' => $data['text_ar'],
            'edit_reason' => $data['edit_reason'] ?? null,
            'edited_by' => $user->id,
            'edited_at' => now(),
            'needs_reimplementation' => true,
        ]);

        AuditLog::record(
            user: $user,
            subject: $ref,
            action: 'manual_reference.edited',
            extra: [
                'rule_id' => 'ESP-MANUAL-EDIT',
                'ref_id' => $ref->id,
                'jord' => $ref->jord_ticket,
                'target' => "{$ref->target_type}:{$ref->target_id}",
                'reason' => $data['edit_reason'] ?? null,
            ],
        );

        return response()->json(['reference' => $ref->fresh()]);
    }

    /**
     * Admin marks the reimplementation as done (dev team cleared it).
     * Resets the pending flag AND updates text_ar_original to match
     * current text — future edits diff against the new baseline.
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isSuperuser()) {
            abort(403);
        }

        $ref = ManualReference::findOrFail($id);
        $ref->update([
            'needs_reimplementation' => false,
            'text_ar_original' => $ref->text_ar,
        ]);

        AuditLog::record(
            user: $user,
            subject: $ref,
            action: 'manual_reference.acknowledged',
            extra: ['rule_id' => 'ESP-MANUAL-ACK', 'ref_id' => $ref->id],
        );

        return response()->json(['reference' => $ref->fresh()]);
    }
}
