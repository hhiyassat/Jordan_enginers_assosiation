<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * StageActions
 *
 * Turns the raw schema.workflow.stages[current].actions[] string list
 * into a structured, bilingual action descriptor that the reviewer UI
 * can render as buttons — and that WorkflowEngine::decide() can use
 * to translate an action id back into the internal decision string.
 *
 * Every action known to the platform is registered here in one place.
 * If a service schema declares an action id that isn't registered,
 * describe() returns null for it — the UI simply skips unknown ids
 * so a schema drift never crashes the reviewer console.
 */
class StageActions
{
    /**
     * Master registry. Keyed by the action id used in schema.workflow.stages.
     *
     *   label_ar / label_en   — bilingual button copy
     *   variant               — UI hint: primary / success / warn / danger / neutral
     *   requires_notes        — reviewer must attach a notes string
     *   decision              — internal Application status transition (null = not a
     *                           status-changing action, e.g. branch selectors)
     *   annotation            — key/value merged into ApplicationReview.annotations
     *   allowed_roles         — which User.role values may take this action
     *
     * @var array<string, array<string, mixed>>
     */
    public const REGISTRY = [
        'approve' => [
            'label_ar'       => 'موافقة',
            'label_en'       => 'Approve',
            'variant'        => 'success',
            'requires_notes' => false,
            'decision'       => Application::STATUS_APPROVED,
            'annotation'     => [],
            'allowed_roles'  => ['staff', 'auditor', 'admin'],
        ],
        'reject' => [
            'label_ar'       => 'رفض',
            'label_en'       => 'Reject',
            'variant'        => 'danger',
            'requires_notes' => true,
            'decision'       => Application::STATUS_REJECTED,
            'annotation'     => [],
            'allowed_roles'  => ['staff', 'auditor', 'admin'],
        ],
        'request_modifications' => [
            'label_ar'       => 'طلب تعديل',
            'label_en'       => 'Request Modifications',
            'variant'        => 'warn',
            'requires_notes' => true,
            'decision'       => Application::STATUS_MODIFICATIONS_REQUESTED,
            'annotation'     => [],
            'allowed_roles'  => ['staff', 'auditor', 'admin'],
        ],
        'override_first_auditor' => [
            'label_ar'       => 'تجاوز قرار المدقق الأول',
            'label_en'       => 'Override First Auditor',
            'variant'        => 'warn',
            'requires_notes' => true,
            'decision'       => Application::STATUS_APPROVED,
            'annotation'     => ['override_first_auditor' => true],
            'allowed_roles'  => ['auditor', 'admin'],
        ],

        // Below actions are applicant/system decisions, not reviewer ones.
        // They're registered so the UI can display them (read-only) but the
        // reviewer console filters them out via allowed_roles.
        'submit'             => ['label_ar' => 'تقديم', 'label_en' => 'Submit', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'confirm_data'       => ['label_ar' => 'تأكيد البيانات', 'label_en' => 'Confirm Data', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'choose_new_system'  => ['label_ar' => 'اختيار النظام الجديد', 'label_en' => 'Choose New System', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'choose_legacy_system' => ['label_ar' => 'اختيار النظام القديم', 'label_en' => 'Choose Legacy System', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'classify_technical'      => ['label_ar' => 'تعديل فني', 'label_en' => 'Technical Modification', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'auditor']],
        'classify_administrative' => ['label_ar' => 'تعديل إداري', 'label_en' => 'Administrative Modification', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'auditor']],
        'choose_excavation'       => ['label_ar' => 'إشراف على الحفريات', 'label_en' => 'Excavation Supervision', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'choose_support_works'    => ['label_ar' => 'إشراف على أعمال التدعيم', 'label_en' => 'Support Works Supervision', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'choose_internal_supervision' => ['label_ar' => 'إشراف من المكتب نفسه', 'label_en' => 'Internal Supervision', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'choose_external_supervision' => ['label_ar' => 'إشراف من مكتب آخر', 'label_en' => 'External Supervision', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'confirm_payment'         => ['label_ar' => 'تأكيد الدفع', 'label_en' => 'Confirm Payment', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'return_to_office'        => ['label_ar' => 'إعادة إلى المكتب', 'label_en' => 'Return to Office', 'variant' => 'warn', 'requires_notes' => true, 'decision' => Application::STATUS_MODIFICATIONS_REQUESTED, 'annotation' => ['return_to_office' => true], 'allowed_roles' => ['staff', 'admin']],
        'issue_certificate'       => ['label_ar' => 'إصدار الشهادة', 'label_en' => 'Issue Certificate', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'open_new_inspection'     => ['label_ar' => 'فتح كشف حسي جديد', 'label_en' => 'Open New Inspection', 'variant' => 'neutral', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'finalize'                => ['label_ar' => 'إنهاء', 'label_en' => 'Finalize', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],

        // Added for CatalogWorkflowsSeeder — cover the non-survey categories.
        'disburse_payment'        => ['label_ar' => 'صرف المستحقات', 'label_en' => 'Disburse Payment', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'register_engineer'       => ['label_ar' => 'تسجيل المهندس', 'label_en' => 'Register Engineer', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'issue_decision'          => ['label_ar' => 'إصدار القرار', 'label_en' => 'Issue Decision', 'variant' => 'success', 'requires_notes' => true, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['auditor', 'admin']],
        'notify_parties'          => ['label_ar' => 'إشعار الأطراف', 'label_en' => 'Notify Parties', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'confirm_data_update'     => ['label_ar' => 'تأكيد تحديث البيانات', 'label_en' => 'Confirm Data Update', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff']],
        'book_slot'               => ['label_ar' => 'حجز الموعد', 'label_en' => 'Book Slot', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant']],
        'serve_document'          => ['label_ar' => 'تسليم الوثيقة', 'label_en' => 'Serve Document', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'admin']],
        'schedule_inspection'     => ['label_ar' => 'جدولة الكشف', 'label_en' => 'Schedule Inspection', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff']],
        'conduct_inspection'      => ['label_ar' => 'إجراء الكشف', 'label_en' => 'Conduct Inspection', 'variant' => 'primary', 'requires_notes' => true, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff', 'auditor']],
        'publish_posting'         => ['label_ar' => 'نشر الإعلان', 'label_en' => 'Publish Posting', 'variant' => 'success', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['staff']],
        'match_candidate'         => ['label_ar' => 'ترشيح مهندس', 'label_en' => 'Match Candidate', 'variant' => 'primary', 'requires_notes' => false, 'decision' => null, 'annotation' => [], 'allowed_roles' => ['applicant', 'staff']],
    ];

    /**
     * Look up a single action's descriptor. Returns null for unknown ids.
     *
     * @return array<string, mixed>|null
     */
    public static function describe(string $actionId): ?array
    {
        $entry = self::REGISTRY[$actionId] ?? null;
        if (!$entry) return null;
        return ['id' => $actionId] + $entry;
    }

    /**
     * Return the descriptors for every action in a stage, filtered by
     * the actor's role (so applicants don't see reviewer buttons and
     * vice-versa). Unknown action ids are silently skipped.
     *
     * @param  list<string> $actionIds  raw list from schema.workflow.stages[].actions
     * @param  string|null  $actorRole  User.role; null = no filter (used in tests)
     * @return list<array<string, mixed>>
     */
    public static function availableFor(array $actionIds, ?string $actorRole = null): array
    {
        $out = [];
        foreach ($actionIds as $id) {
            $desc = self::describe($id);
            if (!$desc) continue;
            if ($actorRole !== null && !in_array($actorRole, $desc['allowed_roles'], true)) continue;
            $out[] = $desc;
        }
        return $out;
    }

    /**
     * Convenience: for the given application + service, resolve the
     * current stage's actions and filter by the actor role.
     *
     * @return list<array<string, mixed>>
     */
    public static function forApplication(Application $app, ServiceDefinition $service, ?string $actorRole = null): array
    {
        $stage = $service->getStage($app->current_stage ?? '');
        if (!$stage) return [];
        /** @var list<string> $actions */
        $actions = is_array($stage['actions'] ?? null) ? $stage['actions'] : [];
        return self::availableFor($actions, $actorRole);
    }
}
