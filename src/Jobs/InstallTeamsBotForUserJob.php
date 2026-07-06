<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\TeamsBotInstallationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstallTeamsBotForUserJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $azureUserId,
        public readonly string $upn,
        public readonly ?string $displayName = null,
    ) {}

    public function handle(TeamsBotInstallationService $installationService): void
    {
        try {
            $installationService->installForUserSync(
                $this->azureUserId,
                $this->upn,
                $this->displayName,
            );
        } catch (Throwable $exception) {
            Log::error('InstallTeamsBotForUserJob fehlgeschlagen', [
                'azure_user_id' => $this->azureUserId,
                'upn' => $this->upn,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
