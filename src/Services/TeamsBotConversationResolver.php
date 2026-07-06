<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Users\Item\Teamwork\InstalledApps\InstalledAppsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Teamwork\InstalledApps\InstalledAppsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use Throwable;

class TeamsBotConversationResolver
{
    protected static GraphServiceClient $graph;

    public function __construct(
        private readonly TeamsSdkRestClient $sdkClient,
    ) {
        $client = new Client;
        self::$graph = $client('teams_bot');
    }

    public function resolve(TeamsBotConversation $conversation): bool
    {
        if ($this->syncFromSdkRest($conversation)) {
            return true;
        }

        if ($this->resolveFromGraph($conversation)) {
            return true;
        }

        return false;
    }

    public function needsResolution(TeamsBotConversation $conversation): bool
    {
        if ($conversation->status === TeamsBotConversationStatus::Failed) {
            return false;
        }

        if ($conversation->status === TeamsBotConversationStatus::Uninstalled) {
            return false;
        }

        if (! $conversation->isReadyForMessaging()) {
            return true;
        }

        return $this->sdkClient->getConversationForUser($conversation->azure_user_id) === null;
    }

    public function ensureSdkConversationRegistered(TeamsBotConversation $conversation): void
    {
        if (! filled($conversation->conversation_id)) {
            return;
        }

        if ($this->sdkClient->getConversationForUser($conversation->azure_user_id) !== null) {
            return;
        }

        $this->registerConversationInSdk($conversation);
    }

    private function syncFromSdkRest(TeamsBotConversation $conversation): bool
    {
        $stored = $this->sdkClient->getConversationForUser($conversation->azure_user_id);

        if ($stored === null) {
            return false;
        }

        $conversationId = $stored['conversationId'] ?? null;
        $serviceUrl = $stored['serviceUrl'] ?? null;
        $tenantId = $stored['tenantId'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            return false;
        }

        $conversation->markActive(
            $conversationId,
            is_string($serviceUrl) && $serviceUrl !== ''
                ? $serviceUrl
                : (string) config('ms-graph-laravel.teams_bot.service_url_fallback'),
            is_string($tenantId) ? $tenantId : null,
        );

        Log::info('Teams Bot Conversation über teams-sdk-rest aufgelöst', [
            'azure_user_id' => $conversation->azure_user_id,
            'conversation_id' => $conversationId,
        ]);

        return true;
    }

    private function resolveFromGraph(TeamsBotConversation $conversation): bool
    {
        $teamsAppId = config('ms-graph-laravel.teams_bot.teams_app_id');
        $azureUserId = $conversation->azure_user_id;

        if (! filled($teamsAppId) || ! filled($azureUserId)) {
            return false;
        }

        try {
            $installationId = $this->findInstallationId($azureUserId, $teamsAppId);

            if ($installationId === null) {
                return false;
            }

            $chat = self::$graph->users()
                ->byUserId($azureUserId)
                ->teamwork()
                ->installedApps()
                ->byUserScopeTeamsAppInstallationId($installationId)
                ->chat()
                ->get()
                ->wait();

            $chatId = $chat->getId();

            if (! is_string($chatId) || $chatId === '') {
                return false;
            }

            $serviceUrl = (string) config('ms-graph-laravel.teams_bot.service_url_fallback');
            $tenantId = is_string(config('ms-graph-laravel.tenant_id')) ? config('ms-graph-laravel.tenant_id') : null;

            $conversation->markActive($chatId, $serviceUrl, $tenantId);
            $this->registerConversationInSdk($conversation);

            Log::info('Teams Bot Conversation über Graph aufgelöst', [
                'azure_user_id' => $azureUserId,
                'conversation_id' => $chatId,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::debug('Teams Bot Conversation Graph-Auflösung fehlgeschlagen', [
                'azure_user_id' => $azureUserId,
                'message' => GraphExceptionMessage::resolve($exception, ''),
            ]);

            return false;
        }
    }

    private function registerConversationInSdk(TeamsBotConversation $conversation): void
    {
        if (! filled($conversation->conversation_id)) {
            return;
        }

        try {
            $this->sdkClient->registerConversation([
                'userAadId' => $conversation->azure_user_id,
                'conversationId' => $conversation->conversation_id,
                'serviceUrl' => $conversation->service_url,
                'tenantId' => $conversation->tenant_id,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Teams Bot Conversation konnte nicht in teams-sdk-rest registriert werden', [
                'azure_user_id' => $conversation->azure_user_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function findInstallationId(string $azureUserId, string $teamsAppId): ?string
    {
        $config = new InstalledAppsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new InstalledAppsRequestBuilderGetQueryParameters;
        $config->queryParameters->expand = ['teamsApp'];
        $config->queryParameters->filter = "teamsApp/id eq '{$teamsAppId}'";

        $response = self::$graph->users()
            ->byUserId($azureUserId)
            ->teamwork()
            ->installedApps()
            ->get($config)
            ->wait();

        $installation = ($response->getValue() ?? [])[0] ?? null;
        $installationId = $installation?->getId();

        return is_string($installationId) && $installationId !== '' ? $installationId : null;
    }
}
