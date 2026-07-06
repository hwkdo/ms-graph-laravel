<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamsBotMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $azureUserId,
        public readonly string $text,
    ) {}

    public function handle(TeamsBotMessagingService $messagingService): void
    {
        try {
            $messagingService->sendMessageSync($this->azureUserId, $this->text);
        } catch (Throwable $exception) {
            Log::error('SendTeamsBotMessageJob fehlgeschlagen', [
                'azure_user_id' => $this->azureUserId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
