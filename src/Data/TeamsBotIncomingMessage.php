<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Data;

use Hwkdo\MsGraphLaravel\Support\TeamsActivityContentParser;

class TeamsBotIncomingMessage
{
    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    public function __construct(
        public readonly string $event,
        public readonly string $text,
        public readonly string $rawText,
        public readonly ?string $azureUserId,
        public readonly ?string $upn,
        public readonly ?string $displayName,
        public readonly string $conversationType,
        public readonly ?string $conversationId,
        public readonly ?string $teamId,
        public readonly ?string $channelId,
        public readonly ?string $messageId,
        public readonly bool $isMention,
        public readonly ?string $quotedText,
        public readonly ?string $quotedSenderName,
        public readonly ?string $quotedSenderAzureId,
        public readonly ?string $quotedMessageId,
        public readonly array $activity,
        public readonly array $conversationRef,
    ) {}

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    public static function fromWebhook(string $event, array $activity, array $conversationRef, ?string $azureUserId): self
    {
        $conversation = is_array($activity['conversation'] ?? null) ? $activity['conversation'] : [];

        $conversationId = $conversationRef['conversationId'] ?? ($conversation['id'] ?? null);
        $teamId = $conversationRef['teamId'] ?? null;
        $channelId = $conversationRef['channelId'] ?? null;

        $conversationType = self::resolveConversationType(
            is_string($conversation['conversationType'] ?? null) ? $conversation['conversationType'] : null,
            is_string($teamId) ? $teamId : null,
            is_string($channelId) ? $channelId : null,
            is_string($conversationId) ? $conversationId : null,
        );

        $from = is_array($activity['from'] ?? null) ? $activity['from'] : [];
        $upn = $from['userPrincipalName'] ?? $from['email'] ?? null;
        $name = $from['name'] ?? null;

        $content = TeamsActivityContentParser::parse($activity);

        return new self(
            event: $event,
            text: $content['text'],
            rawText: is_string($activity['text'] ?? null) ? $activity['text'] : '',
            azureUserId: $azureUserId,
            upn: is_string($upn) && $upn !== '' ? $upn : null,
            displayName: is_string($name) && $name !== '' ? $name : null,
            conversationType: $conversationType,
            conversationId: is_string($conversationId) && $conversationId !== '' ? $conversationId : null,
            teamId: is_string($teamId) && $teamId !== '' ? $teamId : null,
            channelId: is_string($channelId) && $channelId !== '' ? $channelId : null,
            messageId: is_string($activity['id'] ?? null) ? $activity['id'] : null,
            isMention: $event === 'mention' || self::activityMentionsBot($activity),
            quotedText: $content['quotedText'],
            quotedSenderName: $content['quotedSenderName'],
            quotedSenderAzureId: $content['quotedSenderAzureId'],
            quotedMessageId: $content['quotedMessageId'],
            activity: $activity,
            conversationRef: $conversationRef,
        );
    }

    public function hasQuotedContent(): bool
    {
        return filled($this->quotedText);
    }

    public function isChannel(): bool
    {
        return $this->conversationType === 'channel';
    }

    public function isGroupChat(): bool
    {
        return $this->conversationType === 'groupChat';
    }

    public function isDirectMessage(): bool
    {
        return $this->conversationType === 'personal';
    }

    public function sourceLabel(): string
    {
        return match ($this->conversationType) {
            'channel' => 'Microsoft Teams (Kanal)',
            'groupChat' => 'Microsoft Teams (Gruppenchat)',
            default => 'Microsoft Teams (Direktnachricht)',
        };
    }

    private static function resolveConversationType(
        ?string $rawType,
        ?string $teamId,
        ?string $channelId,
        ?string $conversationId,
    ): string {
        if (is_string($rawType) && in_array($rawType, ['personal', 'channel', 'groupChat'], true)) {
            return $rawType;
        }

        if (filled($teamId) && filled($channelId)) {
            return 'channel';
        }

        if (is_string($conversationId) && str_contains($conversationId, '@thread.v2')) {
            return 'groupChat';
        }

        if (is_string($conversationId) && str_starts_with($conversationId, 'a:')) {
            return 'personal';
        }

        return 'personal';
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private static function activityMentionsBot(array $activity): bool
    {
        $entities = $activity['entities'] ?? null;

        if (is_array($entities)) {
            foreach ($entities as $entity) {
                if (is_array($entity) && ($entity['type'] ?? null) === 'mention') {
                    return true;
                }
            }
        }

        $text = $activity['text'] ?? '';

        return is_string($text) && preg_match('/<at>.*?<\/at>/i', $text) === 1;
    }
}
