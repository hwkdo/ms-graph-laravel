<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsBotInstallationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallTeamsBotForTeamJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $teamId,
    ) {}

    public function handle(TeamsBotInstallationService $installationService): void
    {
        try {
            $installationService->installForTeamSync($this->teamId);
        } catch (Throwable $exception) {
            Log::error('InstallTeamsBotForTeamJob fehlgeschlagen', [
                'team_id' => $this->teamId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
