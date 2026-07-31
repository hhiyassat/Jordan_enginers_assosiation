<?php

declare(strict_types=1);

namespace Modules\JeaServices\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Modules\JeaServices\Models\Application;

/**
 * JeaNotificationService — JEA-shaped notification emitters.
 *
 * H-07: previously the Platform `NotificationService` carried
 * `emitApplicationSubmitted(Application)`, `emitApplicationDecided`,
 * `emitPaymentConfirmed`, `emitCertificateIssued`, `emitExpiryReminder`
 * — every method took the JEA `Application` model directly, forcing
 * `app/Services/Notifications/NotificationService.php` to import
 * `Modules\JeaServices\Models\Application`.
 *
 * The Platform primitive is now generic (`sendToUser` + `dispatch`);
 * every JEA-shaped emitter lives here inside the JEA module, and
 * builds a plain Notification via the Platform primitive.
 *
 * WorkflowEngine + JeaDiscipline\RemindExpiries resolve this service
 * out of the container so the caller side stays a one-liner change.
 */
final class JeaNotificationService
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function emitApplicationSubmitted(Application $app): ?Notification
    {
        return $this->send(
            $app,
            type:  'application.submitted',
            title: 'تم تقديم طلبك',
            body:  "تم استلام طلبك رقم {$app->reference_number} وسيتم إشعارك بأي تحديثات.",
        );
    }

    public function emitApplicationDecided(Application $app, User $reviewer, string $decision): ?Notification
    {
        $titles = [
            'approved'                => 'تمت الموافقة على طلبك',
            'rejected'                => 'تم رفض طلبك',
            'modifications_requested' => 'مطلوب تعديل على طلبك',
        ];
        return $this->send(
            $app,
            type:    "application.{$decision}",
            title:   $titles[$decision] ?? 'تحديث على طلبك',
            body:    "تحديث على الطلب رقم {$app->reference_number} من قِبَل {$reviewer->name}.",
            payload: ['decision' => $decision, 'reviewer_name' => $reviewer->name],
        );
    }

    public function emitPaymentConfirmed(Application $app): ?Notification
    {
        return $this->send(
            $app,
            type:  'application.paid',
            title: 'تم تأكيد الدفع',
            body:  "تم تأكيد استلام الدفع لطلبك رقم {$app->reference_number}.",
        );
    }

    public function emitCertificateIssued(Application $app): ?Notification
    {
        return $this->send(
            $app,
            type:  'application.certificate_issued',
            title: 'صدرت شهادتك',
            body:  "صدرت الشهادة لطلبك رقم {$app->reference_number}. يمكنك تنزيلها الآن.",
        );
    }

    /**
     * JORD-80: retention reminder — idempotent per
     * (application_id × kind × threshold_days).
     *
     * @param  'output_validity'|'supervision' $kind
     */
    public function emitExpiryReminder(Application $app, string $kind, int $daysRemaining): ?Notification
    {
        if ($app->applicant === null) {
            throw new \InvalidArgumentException('Application has no applicant to notify.');
        }

        $type = "reminder.{$kind}_expiry";

        $existing = Notification::where('related_type', Application::class)
            ->where('related_id', $app->id)
            ->where('type', $type)
            ->where('payload->threshold_days', $daysRemaining)
            ->first();
        if ($existing) return $existing;

        $labels = [
            'output_validity' => 'صلاحية المخططات',
            'supervision'     => 'صلاحية عقد الإشراف',
        ][$kind] ?? 'صلاحية';

        return $this->send(
            $app,
            type:    $type,
            title:   sprintf('%s — تنتهي خلال %d %s', $labels, $daysRemaining,
                $daysRemaining === 1 ? 'يوم' : 'أيام'),
            body:    sprintf('طلبك رقم %s: %s ستنتهي خلال %d %s. يُنصح باتخاذ الإجراء اللازم.',
                $app->reference_number, $labels, $daysRemaining,
                $daysRemaining === 1 ? 'يوم' : 'أيام'),
            payload: ['threshold_days' => $daysRemaining],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(
        Application $app,
        string $type,
        string $title,
        string $body,
        array $payload = [],
    ): ?Notification {
        $recipient = $app->applicant;
        if ($recipient === null) return null;

        return Notification::create([
            'organization_id' => $recipient->organization_id,
            'user_id'         => $recipient->id,
            'type'            => $type,
            'title'           => $title,
            'body'            => $body,
            'link'            => '/my-applications',
            'related_type'    => Application::class,
            'related_id'      => $app->id,
            'payload'         => $payload ?: null,
        ]);
    }
}
