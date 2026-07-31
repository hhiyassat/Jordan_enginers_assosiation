<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;

/**
 * NotificationService (JORD-9).
 *
 * Platform-neutral notification primitive. Callers hand the recipient
 * (either a `User` or a plain user id) plus a structured payload; this
 * service persists a row on the `notifications` table.
 *
 * H-07: JEA-shaped emitters (emitApplicationSubmitted / …Decided /
 * …PaymentConfirmed / …CertificateIssued / …ExpiryReminder) moved to
 * `Modules\JeaServices\Services\JeaNotificationService` so this
 * primitive no longer imports `Modules\JeaServices\Models\Application`.
 * Any module that wants domain-specific emitters composes them from
 * `dispatch()` inside its own namespace.
 */
final class NotificationService
{
    public function sendToUser(
        int $userId,
        string $type,
        string $titleAr,
        string $titleEn,
        string $bodyAr,
        string $bodyEn,
        ?string $actionUrl = null,
    ): ?Notification {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        return $this->dispatch(
            recipient: $user,
            type:      $type,
            title:     $titleAr,
            body:      $bodyAr,
            link:      $actionUrl,
            payload:   ['title_en' => $titleEn, 'body_en' => $bodyEn],
        );
    }

    /**
     * Central builder. Every module-specific emitter funnels through here
     * so organization_id, related_type/id, and payload defaults land in
     * one place.
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string|null  $relatedType  fully-qualified class name of the related model
     * @param  int|string|null  $relatedId
     */
    public function dispatch(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        ?string $relatedType = null,
        int|string|null $relatedId = null,
        array $payload = [],
    ): Notification {
        return Notification::create([
            'organization_id' => $recipient->organization_id,
            'user_id'         => $recipient->id,
            'type'            => $type,
            'title'           => $title,
            'body'            => $body,
            'link'            => $link,
            'related_type'    => $relatedType,
            'related_id'      => $relatedId,
            'payload'         => $payload ?: null,
        ]);
    }
}
