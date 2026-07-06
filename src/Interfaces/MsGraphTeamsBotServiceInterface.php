<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Illuminate\Support\Collection;

interface MsGraphTeamsBotServiceInterface
{
    public function isEnabled(): bool;

    public function installForUser(string $azureUserId, string $upn, ?string $displayName = null): void;

    public function sendMessage(string $azureUserId, string $text): void;

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
