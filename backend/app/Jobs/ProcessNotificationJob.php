<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessNotificationJob — CS-02.
 *
 * Async notification insertion. Used by callers that want to keep
 * their request thread short (e.g. security-event notifications that
 * fire from auth flows). Callers that need the notification row
 * visible before the HTTP response returns should call
 * `NotificationService::sendToUser` synchronously instead.
 *
 * Idempotency: `WithoutOverlapping` keyed by
 * (user_id, type, correlation_id) prevents duplicate insertion when a
 * single logical event is dispatched twice — the second worker no-ops.
 */
class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public int $timeout = 30;

    public function __construct(
        public int $userId,
        public string $type,
        public string $titleAr,
        public string $titleEn,
        public string $bodyAr,
        public string $bodyEn,
        public ?string $actionUrl = null,
        public ?string $correlationId = null,
    ) {
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        $key = sprintf('%d|%s|%s', $this->userId, $this->type, $this->correlationId ?? '');
        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(15)
                ->expireAfter(60),
        ];
    }

    public function handle(NotificationService $notificationService): void
    {
        Log::info('ProcessNotificationJob: sending', [
            'user_id'        => $this->userId,
            'type'           => $this->type,
            'correlation_id' => $this->correlationId,
            'attempt'        => $this->attempts(),
        ]);

        $notificationService->sendToUser(
            userId:    $this->userId,
            type:      $this->type,
            titleAr:   $this->titleAr,
            titleEn:   $this->titleEn,
            bodyAr:    $this->bodyAr,
            bodyEn:    $this->bodyEn,
            actionUrl: $this->actionUrl,
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNotificationJob failed permanently', [
            'user_id'        => $this->userId,
            'type'           => $this->type,
            'correlation_id' => $this->correlationId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
