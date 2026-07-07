<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Jobs\InstallTeamsBotForTeamJob;
use Hwkdo\MsGraphLaravel\Jobs\InstallTeamsBotForUserJob;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Chats\Item\InstalledApps\InstalledAppsRequestBuilderGetQueryParameters as ChatInstalledAppsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Chats\Item\InstalledApps\InstalledAppsRequestBuilderGetRequestConfiguration as ChatInstalledAppsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Chats\Item\InstalledApps\Item\Upgrade\UpgradePostRequestBody as ChatUpgradePostRequestBody;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\TeamsAppInstallation;
use Microsoft\Graph\Generated\Models\UserScopeTeamsAppInstallation;
use Microsoft\Graph\Generated\Teams\Item\InstalledApps\InstalledAppsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Teams\Item\InstalledApps\InstalledAppsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Teams\Item\InstalledApps\Item\Upgrade\UpgradePostRequestBody;
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

    public function installForTeam(string $teamId): void
    {
        if (! config('ms-graph-laravel.teams_bot.enabled')) {
            throw new RuntimeException('Teams Bot ist deaktiviert.');
        }

        InstallTeamsBotForTeamJob::dispatch($teamId);
    }

    public function installForTeamSync(string $teamId): void
    {
        $teamsAppId = config('ms-graph-laravel.teams_bot.teams_app_id');

        if (! filled($teamsAppId)) {
            throw new RuntimeException('MSGRAPH_TEAMS_APP_CATALOG_ID ist nicht konfiguriert.');
        }

        try {
            $installation = new TeamsAppInstallation;
            $installation->setAdditionalData([
                'teamsApp@odata.bind' => 'https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/'.$teamsAppId,
            ]);

            self::$graph->teams()
                ->byTeamId($teamId)
                ->installedApps()
                ->post($installation)
                ->wait();
        } catch (Throwable $exception) {
            if (self::isAlreadyInstalledError($exception)) {
                Log::info('Teams Bot bereits im Team installiert, versuche Upgrade', ['team_id' => $teamId]);
                $this->upgradeTeamApp($teamId, (string) $teamsAppId);

                return;
            }

            $message = self::appendTeamInstallationHint(GraphExceptionMessage::resolve(
                $exception,
                'Unbekannter Fehler bei der Team-Installation.',
            ));

            Log::error('Teams Bot Team-Installation fehlgeschlagen', [
                'team_id' => $teamId,
                'message' => $message,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
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

    /**
     * Aktualisiert die Team-Installation auf die neueste veröffentlichte Version.
     *
     * Nötig, wenn eine ältere App-Version im Team installiert ist, deren Bot noch
     * keinen Team-Scope besitzt (sonst: BotNotInConversationRoster beim Senden).
     */
    public function installForChatSync(string $chatId): void
    {
        $teamsAppId = config('ms-graph-laravel.teams_bot.teams_app_id');

        if (! filled($teamsAppId)) {
            throw new RuntimeException('MSGRAPH_TEAMS_APP_CATALOG_ID ist nicht konfiguriert.');
        }

        try {
            $installation = new TeamsAppInstallation;
            $installation->setAdditionalData([
                'teamsApp@odata.bind' => 'https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/'.$teamsAppId,
            ]);

            self::$graph->chats()
                ->byChatId($chatId)
                ->installedApps()
                ->post($installation)
                ->wait();
        } catch (Throwable $exception) {
            if (self::isAlreadyInstalledError($exception)) {
                Log::info('Teams Bot bereits im Gruppenchat installiert, versuche Upgrade', ['chat_id' => $chatId]);
                $this->upgradeChatApp($chatId, (string) $teamsAppId);

                return;
            }

            $message = self::appendChatInstallationHint(GraphExceptionMessage::resolve(
                $exception,
                'Unbekannter Fehler bei der Gruppenchat-Installation.',
            ));

            Log::error('Teams Bot Gruppenchat-Installation fehlgeschlagen', [
                'chat_id' => $chatId,
                'message' => $message,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
    }

    private function upgradeChatApp(string $chatId, string $teamsAppId): void
    {
        $config = new ChatInstalledAppsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new ChatInstalledAppsRequestBuilderGetQueryParameters;
        $config->queryParameters->expand = ['teamsApp'];

        $response = self::$graph->chats()
            ->byChatId($chatId)
            ->installedApps()
            ->get($config)
            ->wait();

        foreach ($response?->getValue() ?? [] as $installedApp) {
            if ($installedApp->getTeamsApp()?->getId() !== $teamsAppId) {
                continue;
            }

            $installationId = $installedApp->getId();

            if (! is_string($installationId) || $installationId === '') {
                continue;
            }

            self::$graph->chats()
                ->byChatId($chatId)
                ->installedApps()
                ->byTeamsAppInstallationId($installationId)
                ->upgrade()
                ->post(new ChatUpgradePostRequestBody)
                ->wait();

            Log::info('Teams Bot Gruppenchat-Installation auf neueste Version aktualisiert', [
                'chat_id' => $chatId,
                'installation_id' => $installationId,
            ]);

            return;
        }
    }

    private static function appendChatInstallationHint(string $message): string
    {
        if (str_contains($message, 'Missing role permissions') || str_contains($message, 'Authorization_RequestDenied')) {
            return $message.' Hinweis: Für die Gruppenchat-Installation benötigt die Bot-App die Application-Berechtigung '
                .'TeamsAppInstallation.ReadWriteForChat.All (Admin Consent erforderlich), und der Bot muss den '
                .'Manifest-Scope groupChat besitzen.';
        }

        return $message;
    }

    private function upgradeTeamApp(string $teamId, string $teamsAppId): void
    {
        $config = new InstalledAppsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new InstalledAppsRequestBuilderGetQueryParameters;
        $config->queryParameters->expand = ['teamsApp'];

        $response = self::$graph->teams()
            ->byTeamId($teamId)
            ->installedApps()
            ->get($config)
            ->wait();

        foreach ($response?->getValue() ?? [] as $installedApp) {
            if ($installedApp->getTeamsApp()?->getId() !== $teamsAppId) {
                continue;
            }

            $installationId = $installedApp->getId();

            if (! is_string($installationId) || $installationId === '') {
                continue;
            }

            self::$graph->teams()
                ->byTeamId($teamId)
                ->installedApps()
                ->byTeamsAppInstallationId($installationId)
                ->upgrade()
                ->post(new UpgradePostRequestBody)
                ->wait();

            Log::info('Teams Bot Team-Installation auf neueste Version aktualisiert', [
                'team_id' => $teamId,
                'installation_id' => $installationId,
            ]);

            return;
        }
    }

    private static function appendTeamInstallationHint(string $message): string
    {
        if (str_contains($message, 'Missing role permissions') || str_contains($message, 'Authorization_RequestDenied')) {
            return $message.' Hinweis: Für die Team-Installation benötigt die Bot-App die Application-Berechtigung '
                .'TeamsAppInstallation.ReadWriteForTeam.All (Admin Consent erforderlich).';
        }

        return $message;
    }
}
