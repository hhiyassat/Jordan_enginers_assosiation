<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Application;
use App\Models\Notification;
use App\Models\User;

/**
 * NotificationService (JORD-9).
 *
 * WorkflowEngine hooks call the emit* methods at the key transitions.
 * Each method builds a single row using dispatch() so callers can't
 * accidentally forget the organization_id (the biggest foot-gun on a
 * multi-tenant setup).
 *
 * Deliberately narrow surface: emitApplicationSubmitted,
 * emitApplicationDecided, emitPaymentConfirmed, emitCertificateIssued.
 * Adding a new event type = one new method here + one new label on the
 * frontend. Broadcasting / email fan-out can be layered on top later
 * without changing any callsites.
 */
final class NotificationService
{
    public function emitApplicationSubmitted(Application $app): Notification
    {
        return $this->dispatch(
            $app->applicant,
            type:  'application.submitted',
            title: 'تم تقديم طلبك',
            body:  "تم استلام طلبك رقم {$app->reference_number} وسيتم إشعارك بأي تحديثات.",
            link:  "/my-applications",
            app:   $app,
        );
    }

    /**
     * Fired after a reviewer approves / rejects / requests modifications.
     * $decision is the ApplicationReview.decision value.
     */
    public function emitApplicationDecided(Application $app, User $reviewer, string $decision): Notification
    {
        $titles = [
            'approved'                => 'تمت الموافقة على طلبك',
            'rejected'                => 'تم رفض طلبك',
            'modifications_requested' => 'مطلوب تعديل على طلبك',
        ];
        $title = $titles[$decision] ?? 'تحديث على طلبك';
        $body  = "تحديث على الطلب رقم {$app->reference_number} من قِبَل {$reviewer->name}.";

        return $this->dispatch(
            $app->applicant,
            type:    "application.{$decision}",
            title:   $title,
            body:    $body,
            link:    "/my-applications",
            app:     $app,
            payload: ['decision' => $decision, 'reviewer_name' => $reviewer->name],
        );
    }

    public function emitPaymentConfirmed(Application $app): Notification
    {
        return $this->dispatch(
            $app->applicant,
            type:  'application.paid',
            title: 'تم تأكيد الدفع',
            body:  "تم تأكيد استلام الدفع لطلبك رقم {$app->reference_number}.",
            link:  "/my-applications",
            app:   $app,
        );
    }

    public function emitCertificateIssued(Application $app): Notification
    {
        return $this->dispatch(
            $app->applicant,
            type:  'application.certificate_issued',
            title: 'صدرت شهادتك',
            body:  "صدرت الشهادة لطلبك رقم {$app->reference_number}. يمكنك تنزيلها الآن.",
            link:  "/my-applications",
            app:   $app,
        );
    }

    /**
     * Central builder. Every emit* method funnels through here so
     * organization_id, related_type/id, and payload defaults land in one place.
     *
     * @param  array<string, mixed> $payload
     */
    private function dispatch(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        ?Application $app = null,
        array $payload = [],
    ): Notification {
        return Notification::create([
            'organization_id' => $recipient->organization_id,
            'user_id'         => $recipient->id,
            'type'            => $type,
            'title'           => $title,
            'body'            => $body,
            'link'            => $link,
            'related_type'    => $app ? Application::class : null,
            'related_id'      => $app?->id,
            'payload'         => $payload ?: null,
        ]);
    }
}
