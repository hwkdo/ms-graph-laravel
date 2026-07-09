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
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

class TeamsChatMessageService
{
    private const CHAT_LIMIT = 25;

    private const MESSAGE_LIMIT = 40;

    protected GraphServiceClient $graph;

    public function __construct(?GraphServiceClient $graph = null, string $graphRegistration = 'teams_bot')
    {
        if ($graph instanceof GraphServiceClient) {
            $this->graph = $graph;

            return;
        }

        $client = new Client;
        $this->graph = $client($graphRegistration);
    }

    /**
     * Lädt eingebettete Medien (z. B. Bilder) aus einer Teams-Chatnachricht.
     *
     * @return list<array{filename: string, mimeType: string, contents: string}>
     */
    public function fetchHostedContents(string $chatId, string $messageId): array
    {
        try {
            $response = $this->graph
                ->chats()
                ->byChatId($chatId)
                ->messages()
                ->byChatMessageId($messageId)
                ->hostedContents()
                ->get()
                ->wait();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                GraphExceptionMessage::resolve($exception, 'Teams-Nachrichtenmedien konnten nicht geladen werden.'),
                0,
                $exception,
            );
        }

        $files = [];
        $index = 0;

        foreach ($response?->getValue() ?? [] as $hostedContent) {
            $hostedContentId = $hostedContent->getId();

            if (! is_string($hostedContentId) || $hostedContentId === '') {
                continue;
            }

            $contents = $this->downloadHostedContent($chatId, $messageId, $hostedContentId);

            if ($contents === '') {
                continue;
            }

            $mimeType = $this->resolveMimeType($hostedContent->getContentType(), $contents);
            $index++;
            $files[] = [
                'filename' => $this->buildFilename($index, $mimeType),
                'mimeType' => $mimeType,
                'contents' => $contents,
            ];
        }

        return $files;
    }

    /**
     * Teams-Weiterleitungen (schema.skype.com/Forward) enthalten im Bot-Webhook oft keinen Absender.
     * Sucht die Originalnachricht in den 1:1-Chats des Weiterleitenden per Graph.
     *
     * @return array{azureUserId: ?string, displayName: ?string}
     */
    public function lookupForwardedMessageSender(string $actorAzureUserId, string $quotedText, ?string $excludeChatId = null): array
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

    private function downloadHostedContent(string $chatId, string $messageId, string $hostedContentId): string
    {
        try {
            $stream = $this->graph
                ->chats()
                ->byChatId($chatId)
                ->messages()
                ->byChatMessageId($messageId)
                ->hostedContents()
                ->byChatMessageHostedContentId($hostedContentId)
                ->content()
                ->get()
                ->wait();
        } catch (Throwable) {
            return '';
        }

        if (! $stream instanceof StreamInterface) {
            return '';
        }

        return (string) $stream->getContents();
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

    private function resolveMimeType(?string $declared, string $contents): string
    {
        if (is_string($declared) && trim($declared) !== '' && str_contains($declared, '/')) {
            return trim($declared);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $detected = finfo_buffer($finfo, $contents);
        finfo_close($finfo);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    private function buildFilename(int $index, string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return sprintf('teams-bild-%d.%s', $index, $extension);
    }
}
