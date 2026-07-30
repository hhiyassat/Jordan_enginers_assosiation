<?php

namespace App\Jobs;

use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public string $type,
        public string $titleAr,
        public string $titleEn,
        public string $bodyAr,
        public string $bodyEn,
        public ?string $actionUrl = null,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendToUser(
            userId: $this->userId,
            type: $this->type,
            titleAr: $this->titleAr,
            titleEn: $this->titleEn,
            bodyAr: $this->bodyAr,
            bodyEn: $this->bodyEn,
            actionUrl: $this->actionUrl
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNotificationJob failed permanently', [
            'user_id' => $this->userId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
