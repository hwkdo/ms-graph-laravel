<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
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

class TeamsAppInstallationService
{
    protected static GraphServiceClient $graph;

    public function __construct(string $graphRegistration = 'teams_bot')
    {
        $client = new Client;
        self::$graph = $client($graphRegistration);
    }

    public function installAppForUser(string $azureUserId, string $teamsAppId): void
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

    public function installForTeamSync(string $teamId, string $teamsAppId): void
    {
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
                $this->upgradeTeamApp($teamId, $teamsAppId);

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

    public function installForChatSync(string $chatId, string $teamsAppId): void
    {
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
                $this->upgradeChatApp($chatId, $teamsAppId);

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

    public function upgradeTeamApp(string $teamId, string $teamsAppId): void
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

    public function upgradeChatApp(string $chatId, string $teamsAppId): void
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

    public static function isAlreadyInstalledError(Throwable $exception): bool
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

    public static function appendInstallationHint(string $message, ?string $botAppId = null): string
    {
        if (str_contains($message, 'app permission policy')) {
            return $message.' Hinweis: Im Teams Admin Center unter Teams-Apps → '
                .'Setup-Richtlinien oder App-Berechtigungsrichtlinien die App für den Benutzer freigeben.';
        }

        if (! str_contains($message, 'Caller is not authorized')) {
            return $message;
        }

        return $message.' Hinweis: Bei TeamsAppInstallation.ReadWriteSelfForUser.All muss im Teams-Manifest '
            .'webApplicationInfo.id (und bots[].botId) exakt der Bot App Registration entsprechen'
            .(filled($botAppId) ? " ({$botAppId})" : '')
            .'. Manifest danach erneut im Org-Katalog veröffentlichen und MSGRAPH_TEAMS_APP_CATALOG_ID ggf. aktualisieren. '
            .'Alternativ: TeamsAppInstallation.ReadWriteForUser.All (Application) + Admin Consent.';
    }

    public static function appendTeamInstallationHint(string $message): string
    {
        if (str_contains($message, 'Missing role permissions') || str_contains($message, 'Authorization_RequestDenied')) {
            return $message.' Hinweis: Für die Team-Installation benötigt die Bot-App die Application-Berechtigung '
                .'TeamsAppInstallation.ReadWriteForTeam.All (Admin Consent erforderlich).';
        }

        return $message;
    }

    public static function appendChatInstallationHint(string $message): string
    {
        if (str_contains($message, 'Missing role permissions') || str_contains($message, 'Authorization_RequestDenied')) {
            return $message.' Hinweis: Für die Gruppenchat-Installation benötigt die Bot-App die Application-Berechtigung '
                .'TeamsAppInstallation.ReadWriteForChat.All (Admin Consent erforderlich), und der Bot muss den '
                .'Manifest-Scope groupChat besitzen.';
        }

        return $message;
    }
}
