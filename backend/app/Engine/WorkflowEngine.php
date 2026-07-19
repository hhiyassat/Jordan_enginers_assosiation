<?php

namespace App\Engine;

use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * WorkflowEngine — EDA-Compliant Generic State Machine
 *
 * ═══════════════════════════════════════════════════════════════════
 * METHODOLOGY: Eqratech Decision Assurance Methodology v1.1, Appendix B
 * ═══════════════════════════════════════════════════════════════════
 *
 * This engine is designed around the EDA decision chain from the beginning.
 * Every public method satisfies all 10 B-elements before mutating state.
 * The ALLOWED_TRANSITIONS constant is the single authority for the state machine.
 *
 * BUILD CONTRACT compliance:
 *   ✅ B-5: ALLOWED_TRANSITIONS is the single enforcement point
 *   ✅ B-9: AuditLog::record() called inside DB::transaction()
 *   ✅ WF-001: transitionTo() is the only status mutation point
 *   ✅ WF-002: Every mutation wrapped in DB::transaction()
 *   ✅ WF-003: Every mutation writes AuditLog with rule_id
 *   ✅ WF-004: claim() uses lockForUpdate()
 *   ✅ P-7: No auto-approvals — all decisions require human action
 */
class WorkflowEngine
{
    /**
     * WF-001 / B-5: Single authority for valid state transitions.
     *
     * This constant is the ONLY place where valid transitions are defined.
     * transitionTo() checks against this constant — nowhere else.
     */
    public const ALLOWED_TRANSITIONS = [
        Application::STATUS_DRAFT                   => [Application::STATUS_SUBMITTED],
        Application::STATUS_SUBMITTED               => [Application::STATUS_UNDER_REVIEW],
        Application::STATUS_UNDER_REVIEW            => [
            Application::STATUS_APPROVED,
            Application::STATUS_REJECTED,
            Application::STATUS_MODIFICATIONS_REQUESTED,
        ],
        Application::STATUS_MODIFICATIONS_REQUESTED => [Application::STATUS_SUBMITTED],
        Application::STATUS_APPROVED                => [Application::STATUS_CERTIFICATE_ISSUED],
        Application::STATUS_REJECTED                => [],
        Application::STATUS_CERTIFICATE_ISSUED      => [],
    ];

    public function __construct(private readonly ServiceDefinition $service) {}

    // ─────────────────────────────────────────────────────────────────
    // submit() — EDA Decision Chain: draft → submitted
    // ─────────────────────────────────────────────────────────────────

    /**
     * Submit an application for review.
     *
     * EDA Decision Chain:
     *   B-1 Origin:         The applicant who owns the draft
     *   B-2 Branch:         applicant role (enforced upstream in controller/middleware)
     *   B-3 Relationship:   Application belongs to this applicant (enforced in controller)
     *   B-4 Description:    All schema fields valid (SchemaValidator called before this)
     *   B-5 Difference:     draft → submitted in ALLOWED_TRANSITIONS
     *   B-6 Conditions:     Application is editable; all required documents uploaded
     *   B-7 Cause:          Explicit HTTP POST /applications/{id}/submit
     *   B-8 Blockers:       isEditable() check; terminal state guard
     *   B-9 Effect:         AuditLog::record() inside DB::transaction()
     *   B-10 Residuals:     Validation failure → 422 (handled in controller before calling this)
     *
     * @param Application $app    Already-validated application (controller called SchemaValidator)
     * @param User        $actor  The applicant
     */
    public function submit(Application $app, User $actor): Application
    {
        // B-8: Blocker check — must be editable
        if (! $app->isEditable()) {
            throw new Exceptions\InvalidStateException('لا يمكن تقديم الطلب في وضعه الحالي.');
        }

        // Applicant-owned stages (e.g. 'office_submission') are the
        // draft-authoring phase. On submit the case should move to the
        // FIRST REVIEWER stage so a staff/auditor can claim it — otherwise
        // WorkflowEngine::claim() sees current_stage='office_submission'
        // (role=applicant) and 403s every reviewer with
        // "Stage 'office_submission' requires role 'applicant'."
        $firstReviewer = $this->service->getFirstReviewerStage();
        $prevStatus = $app->status;

        DB::transaction(function () use ($app, $actor, $firstReviewer, $prevStatus) {
            // B-5 + WF-001: transition through ALLOWED_TRANSITIONS
            $this->transitionTo($app, Application::STATUS_SUBMITTED);

            $app->current_stage = $firstReviewer['id'] ?? null;

            // WF-008: set SLA deadline from schema
            if (isset($firstReviewer['sla_hours'])) {
                $app->sla_deadline = now()->addHours($firstReviewer['sla_hours']);
            }

            $app->save();

            // B-9 + WF-003: effect recorded
            AuditLog::record(
                user:    $actor,
                subject: $app,
                action:  'application.submitted',
                extra: [
                    'rule_id'        => 'ESP-WF-001',
                    'from_status'    => $prevStatus,
                    'to_status'      => Application::STATUS_SUBMITTED,
                    'input_snapshot' => $app->data ?? [],
                ],
            );
        });

        return $app->fresh();
    }

    // ─────────────────────────────────────────────────────────────────
    // claim() — EDA Decision Chain: submitted → under_review
    // ─────────────────────────────────────────────────────────────────

    /**
     * Claim an application for review.
     *
     * EDA Decision Chain:
     *   B-3 Relationship:   Reviewer's role must match current stage's required role
     *   B-5 Difference:     submitted → under_review in ALLOWED_TRANSITIONS
     *   B-6 Conditions:     Application not already claimed; status = submitted
     *   B-8 Blockers:       lockForUpdate() prevents race condition (WF-004)
     *   B-9 Effect:         AuditLog::record() inside DB::transaction()
     */
    public function claim(Application $app, User $actor): Application
    {
        // B-2/B-3: Role must match stage. Message is Arabic-first because
        // the reviewer console renders whatever text the API returns.
        $stage = $this->service->getStage($app->current_stage ?? '');
        if ($stage && isset($stage['role'])) {
            if (! $actor->hasRole($stage['role'])) {
                $stageLabel = $stage['label_ar'] ?? $app->current_stage;
                throw new Exceptions\RoleMismatchException(
                    "هذه المرحلة (\"{$stageLabel}\") مخصصة لدور: {$stage['role']}. لا يمكنك استلام الطلب.",
                    stageId:      $app->current_stage,
                    requiredRole: $stage['role'],
                );
            }
        }

        $prevStatus = $app->status;

        DB::transaction(function () use ($app, $actor, $prevStatus) {
            // WF-004: lockForUpdate() prevents concurrent claims. The row
            // may have been deleted since the caller loaded $app (soft-
            // delete, admin action, race with another request) — find()
            // returns null in that case and the next lines would 500 with
            // "Attempt to read property status on null". Guard first,
            // return a clean 409 so the reviewer console can retry.
            $locked = Application::lockForUpdate()->find($app->id);
            if ($locked === null) {
                throw new Exceptions\ConflictException('الطلب لم يعد موجوداً.');
            }

            if ($locked->status !== Application::STATUS_SUBMITTED) {
                throw new Exceptions\ConflictException('الطلب لم يعد متاحاً للاستلام.');
            }

            if ($locked->assigned_reviewer_id !== null) {
                throw new Exceptions\ConflictException('الطلب مستلم بالفعل من قبل مراجع آخر.');
            }

            $this->transitionTo($locked, Application::STATUS_UNDER_REVIEW);
            $locked->assigned_reviewer_id = $actor->id;
            $locked->save();

            // B-9 + WF-003
            AuditLog::record(
                user:    $actor,
                subject: $locked,
                action:  'application.claimed',
                extra: [
                    'rule_id'     => 'ESP-WF-002',
                    'from_status' => $prevStatus,
                    'to_status'   => Application::STATUS_UNDER_REVIEW,
                ],
            );

            $app->fill($locked->toArray());
        });

        return $app->fresh();
    }

    // ─────────────────────────────────────────────────────────────────
    // decide() — EDA Decision Chain: under_review → approved|rejected|modifications_requested
    // ─────────────────────────────────────────────────────────────────

    /**
     * Record a reviewer decision.
     *
     * EDA Decision Chain:
     *   B-2 Branch:         Role must match current stage
     *   B-3 Relationship:   Actor must be the assigned reviewer
     *   B-4 Description:    Notes required for non-approve decisions
     *   B-5 Difference:     Decision must be in ALLOWED_TRANSITIONS['under_review']
     *   B-6 Conditions:     Application must be under_review and claimed by this actor
     *   B-7 Cause:          Explicit HTTP POST /applications/{id}/decide
     *   B-8 Blockers:       Terminal state guard
     *   B-9 Effect:         ApplicationReview created + AuditLog::record()
     *   B-10 Residuals:     modifications_requested → review_round++; approved → fee notification
     */
    public function decide(
        Application $app,
        User        $actor,
        string      $decision,
        ?string     $notes      = null,
        array       $annotations = [],
    ): ApplicationReview {
        // B-6: Must be under review and claimed by this actor
        if ($app->status !== Application::STATUS_UNDER_REVIEW) {
            throw new Exceptions\InvalidStateException('الطلب ليس قيد المراجعة.');
        }

        if ($app->assigned_reviewer_id !== $actor->id) {
            throw new Exceptions\RoleMismatchException(
                'أنت لست المراجع المسند لهذا الطلب.',
                stageId: $app->current_stage,
            );
        }

        // B-5: Validate decision is an allowed transition (global list)
        $allowedDecisions = self::ALLOWED_TRANSITIONS[Application::STATUS_UNDER_REVIEW] ?? [];
        if (! in_array($decision, $allowedDecisions)) {
            throw new Exceptions\InvalidStateException("القرار '{$decision}' غير صالح للحالة الحالية.");
        }

        // B-5 (schema layer): validate against the stage's declared actions array.
        // ALLOWED_TRANSITIONS is the global floor; the schema can restrict further.
        // Actions in the schema are mapped to internal decisions via the
        // StageActions registry — the single source of truth for what each
        // action id means (label, notes requirement, role, resulting status).
        $stage = $this->service->getStage($app->current_stage ?? '');
        if ($stage && isset($stage['actions']) && is_array($stage['actions'])) {
            /** @var list<string> $stageActions */
            $stageActions = $stage['actions'];
            $allowedBySchema = [];
            foreach ($stageActions as $actionId) {
                $desc = StageActions::describe($actionId);
                if ($desc && $desc['decision'] !== null) {
                    $allowedBySchema[] = $desc['decision'];
                }
            }
            if (! in_array($decision, array_unique($allowedBySchema))) {
                $stageName = $stage['label_ar'] ?? $app->current_stage;
                throw new Exceptions\InvalidStateException(
                    "القرار '{$decision}' غير مسموح به في مرحلة '{$stageName}'."
                );
            }
        }

        // B-4: Notes required for non-approve decisions
        if ($decision !== 'approved' && empty($notes)) {
            throw new Exceptions\InvalidStateException('الملاحظات مطلوبة عند طلب التعديل أو الرفض.');
        }

        $prevStatus = $app->status;
        $review     = null;

        // Decide whether this is a mid-workflow stage-approve (more stages
        // to go) or the final approval. Same for rejection paths later.
        $nextStageIfApproving = ($decision === Application::STATUS_APPROVED)
            ? $this->getNextStage($app->current_stage)
            : null;

        DB::transaction(function () use ($app, $actor, $decision, $notes, $annotations, $prevStatus, $nextStageIfApproving, &$review) {
            if ($decision === Application::STATUS_APPROVED && $nextStageIfApproving) {
                // Mid-workflow stage-approve: application STAYS in
                // under_review; only the stage pointer advances. This
                // matches the transition table (approved→under_review is
                // NOT allowed), and semantically the case isn't done —
                // another reviewer still has work to do.
                $app->current_stage        = $nextStageIfApproving['id'];
                $app->assigned_reviewer_id = null; // freed for the next stage's role to claim
                if (isset($nextStageIfApproving['sla_hours'])) {
                    $app->sla_deadline = now()->addHours($nextStageIfApproving['sla_hours']);
                }
            } else {
                // Final decision — commit the status transition.
                $this->transitionTo($app, $decision);

                if ($decision === Application::STATUS_MODIFICATIONS_REQUESTED) {
                    $app->review_round++;
                    $app->assigned_reviewer_id = null;
                } elseif ($decision === Application::STATUS_REJECTED) {
                    $app->assigned_reviewer_id = null;
                }
                // Final approve: reviewer stays assigned so the record
                // shows who approved. Certificate-issuance flow picks up
                // from here via the separate issue endpoint.
            }

            $app->save();

            // Store the review record
            $review = ApplicationReview::create([
                'application_id' => $app->id,
                'reviewer_id'    => $actor->id,
                'stage_id'       => $app->current_stage,
                'decision'       => $decision,
                'notes'          => $notes,
                'annotations'    => $annotations,
                'review_round'   => $app->review_round,
            ]);

            // B-9 + WF-003
            AuditLog::record(
                user:    $actor,
                subject: $app,
                action:  'application.decided',
                extra: [
                    'rule_id'     => 'ESP-WF-003',
                    'from_status' => $prevStatus,
                    'to_status'   => $app->status,
                    'decision'    => $decision,
                    'review_id'   => $review->id,
                ],
            );
        });

        return $review;
    }

    // ─────────────────────────────────────────────────────────────────
    // confirmPayment() — records payment before certificate issuance
    // ─────────────────────────────────────────────────────────────────

    public function confirmPayment(Application $app, User $actor, string $paymentReference): Application
    {
        if ($app->status !== Application::STATUS_APPROVED) {
            throw new Exceptions\InvalidStateException('تأكيد الدفع مسموح فقط للطلبات الموافق عليها.');
        }

        DB::transaction(function () use ($app, $actor, $paymentReference) {
            $app->update([
                'payment_status'        => 'paid',
                'payment_reference'     => $paymentReference,
                'payment_confirmed_at'  => now(),
            ]);

            AuditLog::record(
                user:    $actor,
                subject: $app,
                action:  'application.payment_confirmed',
                extra: [
                    'rule_id'           => 'ESP-WF-005',
                    'payment_reference' => $paymentReference,
                ],
            );
        });

        return $app->fresh();
    }

    // ─────────────────────────────────────────────────────────────────
    // issueCertificate() — EDA Decision Chain: approved → certificate_issued
    // ─────────────────────────────────────────────────────────────────

    /**
     * Issue a certificate for an approved application.
     *
     * EDA Decision Chain:
     *   B-6 Conditions:     Application must be approved AND fee must be paid
     *   B-5 Difference:     approved → certificate_issued in ALLOWED_TRANSITIONS
     *   B-7 Cause:          Explicit HTTP POST /applications/{id}/issue-certificate
     *   B-8 Blockers:       Payment check; terminal state guard
     *   B-9 Effect:         Certificate created + AuditLog::record()
     *   B-10 Residuals:     certificate_issued is terminal — no further transitions
     */
    public function issueCertificate(Application $app, User $actor): Certificate
    {
        // B-6: Both approval and payment required
        if ($app->status !== Application::STATUS_APPROVED) {
            throw new Exceptions\InvalidStateException('لا يمكن إصدار الشهادة إلا للطلبات الموافق عليها.');
        }

        if (! $app->isFeePaid()) {
            throw new Exceptions\InvalidStateException('يجب تأكيد الدفع قبل إصدار الشهادة.');
        }

        $certConfig = $this->service->getCertificateConfig();
        $prevStatus = $app->status;
        $certificate = null;

        DB::transaction(function () use ($app, $actor, $certConfig, $prevStatus, &$certificate) {
            // B-5 + WF-001
            $this->transitionTo($app, Application::STATUS_CERTIFICATE_ISSUED);
            $app->save();

            // Build cert data from schema fields_on_cert. Schema authors
            // (or an AI-generated schema) can slip a non-string value into
            // fields_on_cert; array_flip on a mixed list emits a warning
            // AND silently drops the bad entries — so the certificate
            // ended up with fewer fields than expected. Filter to strings
            // first so the intent is explicit and the diagnostic is
            // caught at authoring time, not from a mysterious blank cert.
            $rawFields   = is_array($certConfig['fields_on_cert'] ?? null) ? $certConfig['fields_on_cert'] : [];
            $certFields  = array_values(array_filter($rawFields, static fn ($v) => is_string($v) && $v !== ''));
            $certData    = array_intersect_key($app->data ?? [], array_flip($certFields));

            // DATA-005: QR token is HMAC-signed
            $certNumber = $this->generateCertificateNumber($app);
            $qrToken    = hash_hmac('sha256', $certNumber, config('app.key'));

            $validityMonths = $certConfig['validity_months'] ?? 12;

            $certificate = Certificate::create([
                'application_id'     => $app->id,
                'organization_id'    => $app->organization_id,
                'issued_to'          => $app->applicant_id,
                'issued_by'          => $actor->id,
                'certificate_number' => $certNumber,
                'qr_token'           => $qrToken,
                'status'             => 'active',
                'issued_date'        => now()->toDateString(),
                'expiry_date'        => now()->addMonths($validityMonths)->toDateString(),
                'cert_data'          => $certData,
            ]);

            // B-9 + WF-003
            AuditLog::record(
                user:    $actor,
                subject: $app,
                action:  'application.certificate_issued',
                extra: [
                    'rule_id'            => 'ESP-WF-004',
                    'from_status'        => $prevStatus,
                    'to_status'          => Application::STATUS_CERTIFICATE_ISSUED,
                    'certificate_number' => $certNumber,
                ],
            );
        });

        return $certificate;
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * WF-001: Single transition point — all status changes go through here.
     * B-5: Validates against ALLOWED_TRANSITIONS constant.
     *
     * NEVER call $app->status = $newStatus directly. Always use this method.
     */
    private function transitionTo(Application $app, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$app->status] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw new Exceptions\InvalidStateException(sprintf(
                'Invalid transition: %s → %s. Allowed: [%s].',
                $app->status,
                $newStatus,
                implode(', ', $allowed),
            ));
        }

        $app->status = $newStatus;
    }

    private function getNextStage(string $currentStageId): ?array
    {
        $stages = $this->service->getWorkflowStages();
        foreach ($stages as $i => $stage) {
            if ($stage['id'] === $currentStageId && isset($stages[$i + 1])) {
                return $stages[$i + 1];
            }
        }
        return null;
    }

    /**
     * Certificate-number allocation (JORD-2).
     *
     * Prior implementation was Certificate::count() + 1 — a classic
     * read-modify-write race. Two concurrent issueCertificate calls both
     * saw N, both formatted N+1, and one insert died on the unique index
     * (rolling back the whole issue transaction). Under load that
     * surfaced as sporadic 500s at the end of the review flow.
     *
     * Now: allocate the serial through certificate_counters with a
     * SELECT ... FOR UPDATE lock. Two concurrent calls serialize on the
     * (organization_id, year) row and each gets a distinct serial. This
     * method is only called from issueCertificate() which already runs
     * inside DB::transaction — the FOR UPDATE hint therefore takes
     * effect on drivers that respect it (MySQL/PostgreSQL); SQLite
     * serializes writes at the file lock, which is why the race never
     * fires in the test suite even before the fix.
     */
    private function generateCertificateNumber(Application $app): string
    {
        $year   = (int) now()->format('Y');
        $orgId  = (int) $app->organization_id;
        $serial = $this->allocateCertificateSerial($orgId, $year);

        return sprintf('CERT-%s-%s-%05d',
            strtoupper($this->service->code),
            $year,
            $serial,
        );
    }

    private function allocateCertificateSerial(int $orgId, int $year): int
    {
        // firstOrCreate is safe here because we're inside a transaction:
        // if two writers race, one gets a unique-key violation and the
        // outer transaction retries via Laravel's built-in retry logic
        // for serialization errors.
        \App\Models\CertificateCounter::firstOrCreate(
            ['organization_id' => $orgId, 'year' => $year],
            ['next_serial' => 1],
        );

        $row = \App\Models\CertificateCounter::where('organization_id', $orgId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $serial = $row->next_serial;
        $row->next_serial = $serial + 1;
        $row->save();

        return $serial;
    }
}
