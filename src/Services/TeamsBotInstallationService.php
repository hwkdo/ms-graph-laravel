<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Jobs\InstallTeamsBotForUserJob;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\UserScopeTeamsAppInstallation;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Throwable;

class TeamsBotInstallationService
{
    protected static GraphServiceClient $graph;

    public function __construct(
        private readonly TeamsBotConversationResolver $conversationResolver,
    ) {
        $client = new Client;
        self::$graph = $client('teams_bot');
    }

    public function installForUser(string $azureUserId, string $upn, ?string $displayName = null): void
    {
        if (! config('ms-graph-laravel.teams_bot.enabled')) {
            throw new RuntimeException('Teams Bot ist deaktiviert.');
        }

        InstallTeamsBotForUserJob::dispatch($azureUserId, $upn, $displayName);
    }

    public function installForUserSync(string $azureUserId, string $upn, ?string $displayName = null): TeamsBotConversation
    {
        $teamsAppId = config('ms-graph-laravel.teams_bot.teams_app_id');

        if (! filled($teamsAppId)) {
            throw new RuntimeException('MSGRAPH_TEAMS_APP_CATALOG_ID ist nicht konfiguriert.');
        }

        $conversation = TeamsBotConversation::query()->updateOrCreate(
            ['azure_user_id' => $azureUserId],
            [
                'upn' => $upn,
                'display_name' => $displayName,
                'status' => TeamsBotConversationStatus::Pending,
                'last_error' => null,
            ],
        );

        try {
            $this->installAppForUser($azureUserId, $teamsAppId);
            $this->finalizeInstallation($conversation, $azureUserId, $upn);
        } catch (Throwable $exception) {
            if (self::isAlreadyInstalledError($exception)) {
                Log::info('Teams Bot bereits installiert, Conversation wird aufgelöst', [
                    'azure_user_id' => $azureUserId,
                    'upn' => $upn,
                ]);
                $this->finalizeInstallation($conversation, $azureUserId, $upn);

                return $conversation->fresh();
            }

            $message = self::appendInstallationHint(GraphExceptionMessage::resolve(
                $exception,
                'Unbekannter Fehler bei der Bot-Installation.',
            ));
            $conversation->markFailed($message);

            Log::error('Teams Bot Installation fehlgeschlagen', [
                'azure_user_id' => $azureUserId,
                'upn' => $upn,
                'message' => $message,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }

        return $conversation->fresh();
    }

    private function installAppForUser(string $azureUserId, string $teamsAppId): void
    {
        $installation = new UserScopeTeamsAppInstallation;
        $installation->setAdditionalData([
            'teamsApp@odata.bind' => 'https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/'.$teamsAppId,
        ]);

        self::$graph->users()
            ->byUserId($azureUserId)
            ->teamwork()
            ->installedApps()
            ->post($installation)
            ->wait();
    }

    private function finalizeInstallation(TeamsBotConversation $conversation, string $azureUserId, string $upn): void
    {
        $conversation->update([
            'status' => TeamsBotConversationStatus::Pending,
            'conversation_id' => null,
            'service_url' => null,
            'installed_at' => now(),
            'last_error' => null,
        ]);

        $conversation = $conversation->fresh();

        if (! $this->conversationResolver->resolve($conversation)) {
            Log::info('Teams Bot installiert, Conversation noch nicht auflösbar', [
                'azure_user_id' => $azureUserId,
                'upn' => $upn,
            ]);
        }
    }

    private static function isAlreadyInstalledError(Throwable $exception): bool
    {
        if ($exception instanceof ODataError) {
            $code = $exception->getError()?->getCode();

            if ($code === 'Conflict') {
                return true;
            }

            return str_contains($exception->getPrimaryErrorMessage(), 'already exists');
        }

        $previous = $exception->getPrevious();

        return $previous instanceof Throwable && self::isAlreadyInstalledError($previous);
    }

    public function getInstallationStatus(string $azureUserId): ?TeamsBotConversation
    {
        return TeamsBotConversation::query()
            ->where('azure_user_id', $azureUserId)
            ->first();
    }

    private static function appendInstallationHint(string $message): string
    {
        if (str_contains($message, 'app permission policy')) {
            return $message.' Hinweis: Im Teams Admin Center unter Teams-Apps → '
                .'Setup-Richtlinien oder App-Berechtigungsrichtlinien die App für den Benutzer freigeben.';
        }

        if (! str_contains($message, 'Caller is not authorized')) {
            return $message;
        }

        $botAppId = config('ms-graph-laravel.teams_bot.app_id');

        return $message.' Hinweis: Bei TeamsAppInstallation.ReadWriteSelfForUser.All muss im Teams-Manifest '
            .'webApplicationInfo.id (und bots[].botId) exakt der Bot App Registration entsprechen'
            .(filled($botAppId) ? " ({$botAppId})" : '')
            .'. Manifest danach erneut im Org-Katalog veröffentlichen und MSGRAPH_TEAMS_APP_CATALOG_ID ggf. aktualisieren. '
            .'Alternativ: TeamsAppInstallation.ReadWriteForUser.All (Application) + Admin Consent.';
    }
}
