<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Microsoft\Graph\Generated\Models\ConversationMember;
use Microsoft\Graph\Generated\Teams\TeamsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Teams\TeamsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Chats\ChatsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Chats\ChatsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Throwable;

class TeamsBotChannelDirectoryService
{
    private const MAX_TEAMS = 15;

    protected GraphServiceClient $graph;

    public function __construct(?GraphServiceClient $graph = null)
    {
        if ($graph instanceof GraphServiceClient) {
            $this->graph = $graph;

            return;
        }

        $registration = (string) config('ms-graph-laravel.teams_bot.graph_registration', 'teams_bot');

        $client = new Client;
        $this->graph = $client($registration);
    }

    /**
     * Sucht Teams anhand des Namens direkt über die Microsoft Graph API (Prefix-Filter).
     *
     * @return list<array{teamId: string, teamName: string}>
     */
    public function searchTeams(string $search, int $limit = self::MAX_TEAMS): array
    {
        $term = trim($search);

        if ($term === '' || mb_strlen($term) < 2) {
            return [];
        }

        $escaped = str_replace("'", "''", $term);

        $config = new TeamsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new TeamsRequestBuilderGetQueryParameters;
        $config->queryParameters->filter = "startswith(displayName,'{$escaped}')";
        $config->queryParameters->select = ['id', 'displayName'];
        $config->queryParameters->top = $limit;

        try {
            $response = $this->graph->teams()->get($config)->wait();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                GraphExceptionMessage::resolve($exception, 'Team-Suche fehlgeschlagen.'),
                0,
                $exception,
            );
        }

        $results = [];

        foreach ($response?->getValue() ?? [] as $team) {
            $teamId = $team->getId();

            if (! is_string($teamId) || $teamId === '') {
                continue;
            }

            $teamName = $team->getDisplayName();

            $results[] = [
                'teamId' => $teamId,
                'teamName' => is_string($teamName) && $teamName !== '' ? $teamName : $teamId,
            ];
        }

        return $results;
    }

    /**
     * Listet alle Kanäle eines Teams direkt über die Microsoft Graph API.
     *
     * @return list<array{channelId: string, channelName: string}>
     */
    public function listChannels(string $teamId): array
    {
        if (trim($teamId) === '') {
            return [];
        }

        try {
            $response = $this->graph->teams()
                ->byTeamId($teamId)
                ->channels()
                ->get()
                ->wait();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                GraphExceptionMessage::resolve($exception, 'Kanäle konnten nicht geladen werden.'),
                0,
                $exception,
            );
        }

        $results = [];

        foreach ($response?->getValue() ?? [] as $channel) {
            $channelId = $channel->getId();

            if (! is_string($channelId) || $channelId === '') {
                continue;
            }

            $channelName = $channel->getDisplayName();

            $results[] = [
                'channelId' => $channelId,
                'channelName' => is_string($channelName) && $channelName !== '' ? $channelName : $channelId,
            ];
        }

        return $results;
    }

    /**
     * Listet die Gruppenchats eines Users (chatType 'group') über die Microsoft Graph API.
     *
     * @return list<array{chatId: string, label: string}>
     */
    public function listUserGroupChats(string $userId, int $limit = 30): array
    {
        if (trim($userId) === '') {
            return [];
        }

        $config = new ChatsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new ChatsRequestBuilderGetQueryParameters;
        $config->queryParameters->filter = "chatType eq 'group'";
        $config->queryParameters->expand = ['members'];
        $config->queryParameters->top = $limit;

        try {
            $response = $this->graph->users()
                ->byUserId($userId)
                ->chats()
                ->get($config)
                ->wait();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                GraphExceptionMessage::resolve($exception, 'Gruppenchats konnten nicht geladen werden.'),
                0,
                $exception,
            );
        }

        $results = [];

        foreach ($response?->getValue() ?? [] as $chat) {
            $chatId = $chat->getId();

            if (! is_string($chatId) || $chatId === '') {
                continue;
            }

            $results[] = [
                'chatId' => $chatId,
                'label' => $this->buildChatLabel($chat->getTopic(), $chat->getMembers(), $chatId),
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, ConversationMember>|null  $members
     */
    private function buildChatLabel(?string $topic, ?array $members, string $chatId): string
    {
        if (is_string($topic) && trim($topic) !== '') {
            return trim($topic);
        }

        $names = [];

        foreach ($members ?? [] as $member) {
            $name = $member->getDisplayName();

            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        if ($names === []) {
            return 'Gruppenchat '.mb_substr($chatId, 0, 24);
        }

        $shown = array_slice($names, 0, 3);
        $label = implode(', ', $shown);

        if (count($names) > 3) {
            $label .= ' +'.(count($names) - 3);
        }

        return $label;
    }
}
