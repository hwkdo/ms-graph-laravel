<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsActivityFeedNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamsActivityFeedNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $azureUserId,
        public readonly string $previewText,
        public readonly ?string $actorText = null,
        public readonly ?string $topicTitle = null,
        public readonly ?string $webUrl = null,
    ) {}

    public function handle(TeamsActivityFeedNotificationService $notificationService): void
    {
        try {
            $notificationService->sendNotificationSync(
                $this->azureUserId,
                $this->previewText,
                $this->actorText,
                $this->topicTitle,
                $this->webUrl,
            );
        } catch (Throwable $exception) {
            Log::error('SendTeamsActivityFeedNotificationJob fehlgeschlagen', [
                'azure_user_id' => $this->azureUserId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
