<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamsBotChannelMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $teamId,
        public readonly string $channelId,
        public readonly string $text,
    ) {}

    public function handle(TeamsBotMessagingService $messagingService): void
    {
        try {
            $messagingService->sendChannelMessageSync($this->teamId, $this->channelId, $this->text);
        } catch (Throwable $exception) {
            Log::error('SendTeamsBotChannelMessageJob fehlgeschlagen', [
                'team_id' => $this->teamId,
                'channel_id' => $this->channelId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
