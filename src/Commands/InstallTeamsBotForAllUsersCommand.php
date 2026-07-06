<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Commands;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Services\TeamsBotInstallationService;
use Illuminate\Console\Command;
use Throwable;

class InstallTeamsBotForAllUsersCommand extends Command
{
    protected $signature = 'ms-graph:teams-bot-install-all
                            {--top=100 : Anzahl Benutzer pro Graph-Seite}
                            {--search= : Optionaler Suchfilter für Benutzer}';

    protected $description = 'Installiert den Teams Bot für alle Entra-Benutzer (paginiert)';

    public function handle(
        MsGraphUserServiceInterface $userService,
        TeamsBotInstallationService $installationService,
    ): int {
        if (! config('ms-graph-laravel.teams_bot.enabled')) {
            $this->error('Teams Bot ist deaktiviert (MSGRAPH_TEAMS_BOT_ENABLED=false).');

            return self::FAILURE;
        }

        $top = max(1, (int) $this->option('top'));
        $search = $this->option('search');
        $nextLink = null;
        $installed = 0;
        $failed = 0;

        do {
            $result = $userService->getUsersPaginated($top, is_string($search) && $search !== '' ? $search : null, $nextLink);
            $users = $result['users'] ?? [];
            $nextLink = $result['nextLink'] ?? null;

            foreach ($users as $user) {
                $azureUserId = $user->getId();
                $upn = $user->getUserPrincipalName();
                $displayName = $user->getDisplayName();

                if (! is_string($azureUserId) || ! is_string($upn) || $upn === '') {
                    continue;
                }

                try {
                    $installationService->installForUser($azureUserId, $upn, is_string($displayName) ? $displayName : null);
                    $installed++;
                    $this->line("Queued: {$upn}");
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn("Fehler für {$upn}: ".$exception->getMessage());
                }
            }
        } while (is_string($nextLink) && $nextLink !== '');

        $this->info("Installation gequeued: {$installed}, Fehler: {$failed}");

        return self::SUCCESS;
    }
}
