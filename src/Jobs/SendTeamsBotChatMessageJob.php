<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamsBotChatMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $chatId,
        public readonly string $text,
    ) {}

    public function handle(TeamsBotMessagingService $messagingService): void
    {
        try {
            $messagingService->sendChatMessageSync($this->chatId, $this->text);
        } catch (Throwable $exception) {
            Log::error('SendTeamsBotChatMessageJob fehlgeschlagen', [
                'chat_id' => $this->chatId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
