<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Chats\Item\Messages\MessagesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Chats\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\ChatMessage;
use Microsoft\Graph\Generated\Users\Item\Chats\ChatsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Chats\ChatsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use Throwable;

class TeamsForwardedMessageSenderLookup
{
    private const CHAT_LIMIT = 25;

    private const MESSAGE_LIMIT = 40;

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
     * Teams-Weiterleitungen (schema.skype.com/Forward) enthalten im Bot-Webhook oft keinen Absender.
     * Sucht die Originalnachricht in den 1:1-Chats des Weiterleitenden per Graph.
     *
     * @return array{azureUserId: ?string, displayName: ?string}
     */
    public function lookup(string $actorAzureUserId, string $quotedText, ?string $excludeChatId = null): array
    {
        $needle = $this->normalizeText($quotedText);

        if ($needle === '') {
            return ['azureUserId' => null, 'displayName' => null];
        }

        try {
            $chatIds = $this->listOneOnOneChatIds($actorAzureUserId);
        } catch (Throwable $exception) {
            Log::warning('Teams: 1:1-Chats für Forward-Absender-Suche nicht abrufbar', [
                'actor_azure_user_id' => $actorAzureUserId,
                'message' => $exception->getMessage(),
            ]);

            return ['azureUserId' => null, 'displayName' => null];
        }

        foreach ($chatIds as $chatId) {
            if ($excludeChatId !== null && $chatId === $excludeChatId) {
                continue;
            }

            $sender = $this->findSenderInChat($chatId, $needle, $actorAzureUserId);

            if ($sender !== null) {
                Log::info('Teams: Original-Absender über Graph-Chat-Suche gefunden', [
                    'actor_azure_user_id' => $actorAzureUserId,
                    'chat_id' => $chatId,
                    'sender_azure_user_id' => $sender['azureUserId'],
                    'sender_display_name' => $sender['displayName'],
                ]);

                return $sender;
            }
        }

        return ['azureUserId' => null, 'displayName' => null];
    }

    /**
     * @return list<string>
     */
    private function listOneOnOneChatIds(string $userId): array
    {
        $config = new ChatsRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new ChatsRequestBuilderGetQueryParameters;
        $config->queryParameters->filter = "chatType eq 'oneOnOne'";
        $config->queryParameters->top = self::CHAT_LIMIT;

        $response = $this->graph->users()
            ->byUserId($userId)
            ->chats()
            ->get($config)
            ->wait();

        $chatIds = [];

        foreach ($response?->getValue() ?? [] as $chat) {
            $chatId = $chat->getId();

            if (is_string($chatId) && $chatId !== '') {
                $chatIds[] = $chatId;
            }
        }

        return $chatIds;
    }

    /**
     * @return array{azureUserId: string, displayName: ?string}|null
     */
    private function findSenderInChat(string $chatId, string $needle, string $actorAzureUserId): ?array
    {
        try {
            $config = new MessagesRequestBuilderGetRequestConfiguration;
            $config->queryParameters = new MessagesRequestBuilderGetQueryParameters;
            $config->queryParameters->top = self::MESSAGE_LIMIT;

            $response = $this->graph->chats()
                ->byChatId($chatId)
                ->messages()
                ->get($config)
                ->wait();
        } catch (Throwable $exception) {
            Log::debug('Teams: Chat-Nachrichten für Forward-Suche nicht lesbar', [
                'chat_id' => $chatId,
                'message' => GraphExceptionMessage::resolve($exception, 'Nachrichten nicht lesbar'),
            ]);

            return null;
        }

        foreach ($response?->getValue() ?? [] as $message) {
            if (! $message instanceof ChatMessage) {
                continue;
            }

            $body = $this->normalizeText((string) ($message->getBody()?->getContent() ?? ''));

            if ($body === '' || ! $this->textsMatch($needle, $body)) {
                continue;
            }

            $sender = $message->getFrom()?->getUser();
            $senderId = $sender?->getId();

            if (! is_string($senderId) || $senderId === '') {
                continue;
            }

            $normalizedSenderId = strtolower($senderId);

            if ($normalizedSenderId === strtolower($actorAzureUserId)) {
                continue;
            }

            $displayName = $sender?->getDisplayName();

            return [
                'azureUserId' => $normalizedSenderId,
                'displayName' => is_string($displayName) && trim($displayName) !== '' ? trim($displayName) : null,
            ];
        }

        return null;
    }

    private function textsMatch(string $needle, string $haystack): bool
    {
        if (str_contains($haystack, $needle)) {
            return true;
        }

        $shortNeedle = mb_substr($needle, 0, 120);

        return $shortNeedle !== '' && str_contains($haystack, $shortNeedle);
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
