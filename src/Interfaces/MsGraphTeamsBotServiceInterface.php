<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Illuminate\Support\Collection;

interface MsGraphTeamsBotServiceInterface
{
    public function isEnabled(): bool;

    public function installForUser(string $azureUserId, string $upn, ?string $displayName = null): void;

    public function installForTeam(string $teamId): void;

    public function installForChat(string $chatId): void;

    public function sendMessage(string $azureUserId, string $text): void;

    /**
     * @return list<array{teamId: string, teamName: string}>
     */
    public function searchTenantTeams(string $search, int $limit = 15): array;

    /**
     * @return list<array{channelId: string, channelName: string}>
     */
    public function listTeamChannels(string $teamId): array;

    public function sendChannelMessage(string $teamId, string $channelId, string $text): void;

    /**
     * @return list<array{chatId: string, label: string}>
     */
    public function listUserGroupChats(string $azureUserId): array;

    public function sendChatMessage(string $chatId, string $text): void;

    public function getConversation(string $azureUserId): ?TeamsBotConversation;

    /**
     * @return Collection<int, TeamsBotConversation>
     */
    public function listConversations(): Collection;

    public function activeConversationCount(): int;

    /**
     * @return array{healthy: bool, service: string|null, base_url: string}
     */
    public function getSdkRestHealthStatus(): array;
}
