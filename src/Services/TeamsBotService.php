<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Illuminate\Support\Collection;

class TeamsBotService implements MsGraphTeamsBotServiceInterface
{
    public function __construct(
        private readonly TeamsBotInstallationService $installationService,
        private readonly TeamsBotMessagingService $messagingService,
        private readonly TeamsBotChannelDirectoryService $channelDirectory,
        private readonly TeamsSdkRestClient $sdkRestClient,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('ms-graph-laravel.teams_bot.enabled')
            && filled(config('ms-graph-laravel.teams_bot.app_id'))
            && filled(config('ms-graph-laravel.teams_bot.app_secret'))
            && filled(config('ms-graph-laravel.teams_sdk_rest.base_url'))
            && filled(config('ms-graph-laravel.teams_sdk_rest.api_key'));
    }

    public function installForUser(string $azureUserId, string $upn, ?string $displayName = null): void
    {
        $this->installationService->installForUser($azureUserId, $upn, $displayName);
    }

    public function installForTeam(string $teamId): void
    {
        $this->installationService->installForTeamSync($teamId);
    }

    public function installForChat(string $chatId): void
    {
        $this->installationService->installForChatSync($chatId);
    }

    public function sendMessage(string $azureUserId, string $text): void
    {
        $this->messagingService->queueMessage($azureUserId, $text);
    }

    /**
     * @return list<array{teamId: string, teamName: string}>
     */
    public function searchTenantTeams(string $search, int $limit = 15): array
    {
        return $this->channelDirectory->searchTeams($search, $limit);
    }

    /**
     * @return list<array{channelId: string, channelName: string}>
     */
    public function listTeamChannels(string $teamId): array
    {
        return $this->channelDirectory->listChannels($teamId);
    }

    public function sendChannelMessage(string $teamId, string $channelId, string $text): void
    {
        $this->messagingService->queueChannelMessage($teamId, $channelId, $text);
    }

    /**
     * @return list<array{chatId: string, label: string}>
     */
    public function listUserGroupChats(string $azureUserId): array
    {
        return $this->channelDirectory->listUserGroupChats($azureUserId);
    }

    public function sendChatMessage(string $chatId, string $text): void
    {
        $this->messagingService->queueChatMessage($chatId, $text);
    }

    public function getConversation(string $azureUserId): ?TeamsBotConversation
    {
        return $this->installationService->getInstallationStatus($azureUserId);
    }

    public function listConversations(): Collection
    {
        return TeamsBotConversation::query()
            ->orderByDesc('updated_at')
            ->get();
    }

    public function activeConversationCount(): int
    {
        return TeamsBotConversation::query()
            ->where('status', TeamsBotConversationStatus::Active)
            ->count();
    }

    public function getSdkRestHealthStatus(): array
    {
        return $this->sdkRestClient->getHealthStatus();
    }
}
