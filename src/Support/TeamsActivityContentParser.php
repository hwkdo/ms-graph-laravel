<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Support;

class TeamsActivityContentParser
{
    /**
     * @param  array<string, mixed>  $activity
     * @return array{
     *     text: string,
     *     quotedText: ?string,
     *     quotedSenderName: ?string,
     *     quotedSenderAzureId: ?string,
     *     quotedMessageId: ?string
     * }
     */
    public static function parse(array $activity): array
    {
        $plainText = is_string($activity['text'] ?? null) ? $activity['text'] : '';
        $htmlSource = self::resolveHtmlSource($activity, $plainText);

        $quotedFromHtml = self::extractQuotedFromHtml($htmlSource);
        $quotedFromMetadata = self::extractQuotedFromMetadata($activity);

        $commandText = filled($quotedFromHtml['remainder']) && $quotedFromHtml['remainder'] !== $htmlSource
            ? $quotedFromHtml['remainder']
            : $plainText;

        $quotedText = filled($quotedFromHtml['text']) ? $quotedFromHtml['text'] : $quotedFromMetadata['text'];
        $quotedSenderName = $quotedFromHtml['sender'] ?? $quotedFromMetadata['sender'];
        $quotedSenderAzureId = self::firstValidAzureUserId(
            $quotedFromHtml['senderAzureId'] ?? null,
            $quotedFromMetadata['senderAzureId'] ?? null,
        );
        $quotedMessageId = self::extractQuotedMessageId($activity, $htmlSource);

        return [
            'text' => self::normalizeCommandText($commandText),
            'quotedText' => filled($quotedText) ? trim((string) $quotedText) : null,
            'quotedSenderName' => filled($quotedSenderName) ? trim((string) $quotedSenderName) : null,
            'quotedSenderAzureId' => filled($quotedSenderAzureId) ? trim((string) $quotedSenderAzureId) : null,
            'quotedMessageId' => filled($quotedMessageId) ? trim((string) $quotedMessageId) : null,
        ];
    }

    /**
     * Teams liefert Zitate oft nur im text/html-Attachment, während activity.text nur die
     * Kommandzeile als Plaintext enthält.
     *
     * @param  array<string, mixed>  $activity
     */
    private static function resolveHtmlSource(array $activity, string $plainText): string
    {
        $attachmentHtml = self::extractHtmlAttachment($activity);

        if ($attachmentHtml !== null && str_contains($attachmentHtml, '<blockquote')) {
            return $attachmentHtml;
        }

        if (str_contains($plainText, '<')) {
            return $plainText;
        }

        return $attachmentHtml ?? $plainText;
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private static function extractHtmlAttachment(array $activity): ?string
    {
        $attachments = $activity['attachments'] ?? [];

        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment) || ($attachment['contentType'] ?? null) !== 'text/html') {
                continue;
            }

            $content = $attachment['content'] ?? null;

            if (is_string($content) && trim($content) !== '') {
                return $content;
            }
        }

        return null;
    }

    /**
     * @return array{remainder: string, text: ?string, sender: ?string, senderAzureId: ?string}
     */
    private static function extractQuotedFromHtml(string $html): array
    {
        if ($html === '' || ! str_contains($html, '<')) {
            return [
                'remainder' => $html,
                'text' => null,
                'sender' => null,
                'senderAzureId' => null,
            ];
        }

        if (preg_match('/<blockquote\b[^>]*>(.*?)<\/blockquote>/is', $html, $blockquoteMatch) !== 1) {
            return [
                'remainder' => $html,
                'text' => null,
                'sender' => null,
                'senderAzureId' => null,
            ];
        }

        $blockquote = $blockquoteMatch[1];
        $remainder = trim((string) preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', ' ', $html));

        $quotedText = null;
        $sender = null;
        $senderAzureId = null;

        if (preg_match('/<p[^>]*itemprop="preview"[^>]*>(.*?)<\/p>/is', $blockquote, $previewMatch) === 1) {
            $quotedText = self::htmlToPlainText($previewMatch[1]);
        } elseif (preg_match('/<div[^>]*itemprop="comment"[^>]*>(.*?)<\/div>/is', $blockquote, $commentMatch) === 1) {
            $quotedText = self::htmlToPlainText($commentMatch[1]);
        } else {
            $quotedText = self::htmlToPlainText($blockquote);
        }

        if (preg_match('/<strong[^>]*itemprop="mri"[^>]*itemid="8:orgid:([0-9a-f-]{36})"[^>]*>(.*?)<\/strong>/is', $blockquote, $senderMatch) === 1) {
            $senderAzureId = strtolower($senderMatch[1]);
            $sender = self::htmlToPlainText($senderMatch[2]);
        } elseif (preg_match('/<div[^>]*itemprop="sender"[^>]*>(.*?)<\/div>/is', $blockquote, $senderMatch) === 1) {
            $sender = self::extractSenderFromSenderBlock($senderMatch[1]);
        } elseif (preg_match('/<span[^>]*itemprop="name"[^>]*>(.*?)<\/span>/is', $blockquote, $senderMatch) === 1) {
            $sender = self::htmlToPlainText($senderMatch[1]);
        } elseif (preg_match('/<strong[^>]*>(.*?)<\/strong>/is', $blockquote, $senderMatch) === 1) {
            $sender = self::htmlToPlainText($senderMatch[1]);
        }

        if ($senderAzureId === null && preg_match('/\bitemid="8:orgid:([0-9a-f-]{36})"/i', $blockquote, $orgIdMatch) === 1) {
            $senderAzureId = strtolower($orgIdMatch[1]);
        }

        return [
            'remainder' => $remainder,
            'text' => filled($quotedText) ? $quotedText : null,
            'sender' => filled($sender) ? $sender : null,
            'senderAzureId' => $senderAzureId,
        ];
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return array{text: ?string, sender: ?string, senderAzureId: ?string}
     */
    private static function extractQuotedFromMetadata(array $activity): array
    {
        $entities = $activity['entities'] ?? [];

        if (is_array($entities)) {
            foreach ($entities as $entity) {
                if (! is_array($entity) || ($entity['type'] ?? null) !== 'quotedReply') {
                    continue;
                }

                $quotedReply = $entity['quotedReply'] ?? $entity;
                if (! is_array($quotedReply)) {
                    continue;
                }

                $preview = $quotedReply['preview'] ?? null;
                $sender = $quotedReply['senderName'] ?? $quotedReply['senderDisplayName'] ?? null;
                $senderAzureId = self::normalizeAzureUserId($quotedReply['senderId'] ?? null);

                if (is_string($preview) && trim($preview) !== '') {
                    return [
                        'text' => trim($preview),
                        'sender' => is_string($sender) && trim($sender) !== '' ? trim($sender) : null,
                        'senderAzureId' => $senderAzureId,
                    ];
                }
            }
        }

        $attachments = $activity['attachments'] ?? [];

        if (! is_array($attachments)) {
            return ['text' => null, 'sender' => null, 'senderAzureId' => null];
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $contentType = $attachment['contentType'] ?? null;
            $content = $attachment['content'] ?? null;

            if (! is_string($content) || trim($content) === '') {
                continue;
            }

            if ($contentType === 'forwardedMessageReference') {
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($content, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $preview = $decoded['originalMessageContent'] ?? null;
                $messageSender = $decoded['originalMessageSender'] ?? null;
                $sender = null;
                $senderAzureId = null;

                if (is_array($messageSender)) {
                    $user = $messageSender['user'] ?? null;

                    if (is_array($user)) {
                        $displayName = $user['displayName'] ?? null;
                        $sender = is_string($displayName) && trim($displayName) !== '' ? trim($displayName) : null;
                        $senderAzureId = self::normalizeAzureUserId($user['id'] ?? null);
                    }
                }

                if (is_string($preview) && trim($preview) !== '') {
                    return [
                        'text' => self::htmlToPlainText(trim($preview)),
                        'sender' => $sender,
                        'senderAzureId' => $senderAzureId,
                    ];
                }
            }

            if ($contentType !== 'messageReference') {
                continue;
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                continue;
            }

            $preview = $decoded['messagePreview'] ?? null;
            $sender = null;
            $senderAzureId = null;
            $messageSender = $decoded['messageSender'] ?? null;

            if (is_array($messageSender)) {
                $user = $messageSender['user'] ?? null;
                $application = $messageSender['application'] ?? null;

                if (is_array($user)) {
                    $displayName = $user['displayName'] ?? null;
                    $sender = is_string($displayName) && trim($displayName) !== '' ? trim($displayName) : null;
                    $senderAzureId = self::normalizeAzureUserId($user['id'] ?? null);
                }

                if ($sender === null && is_array($application)) {
                    $displayName = $application['displayName'] ?? null;
                    $sender = is_string($displayName) && trim($displayName) !== '' ? trim($displayName) : null;
                }
            }

            if (is_string($preview) && trim($preview) !== '') {
                return [
                    'text' => trim($preview),
                    'sender' => $sender,
                    'senderAzureId' => $senderAzureId,
                ];
            }
        }

        return ['text' => null, 'sender' => null, 'senderAzureId' => null];
    }

    private static function normalizeAzureUserId(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/8:orgid:([0-9a-f-]{36})/i', $value, $matches) === 1) {
            return strtolower($matches[1]);
        }

        $resolved = TeamsMemberId::resolveAzureUserId($value);

        if (preg_match('/^[0-9a-f-]{36}$/i', $resolved) === 1) {
            return strtolower($resolved);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private static function extractQuotedMessageId(array $activity, string $htmlSource): ?string
    {
        $candidates = [];

        $plainText = is_string($activity['text'] ?? null) ? $activity['text'] : '';

        if (preg_match('/<quoted[^>]*\bmessageId="([^"]+)"/i', $plainText, $match) === 1) {
            $candidates[] = $match[1];
        }

        if (preg_match('/<blockquote[^>]*\bitemid="([^"]+)"/i', $htmlSource, $match) === 1) {
            $candidates[] = $match[1];
        }

        $entities = $activity['entities'] ?? [];

        if (is_array($entities)) {
            foreach ($entities as $entity) {
                if (! is_array($entity) || ($entity['type'] ?? null) !== 'quotedReply') {
                    continue;
                }

                $quotedReply = $entity['quotedReply'] ?? $entity;

                if (is_array($quotedReply) && is_string($quotedReply['messageId'] ?? null)) {
                    $candidates[] = $quotedReply['messageId'];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private static function firstValidAzureUserId(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private static function normalizeCommandText(string $text): string
    {
        $text = preg_replace('/<at>.*?<\/at>\s*/i', '', $text) ?? $text;
        $text = preg_replace('/<span[^>]*schema\.skype\.com\/Mention[^>]*>.*?<\/span>\s*/is', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function extractSenderFromSenderBlock(string $html): ?string
    {
        if (preg_match('/<span[^>]*itemprop="name"[^>]*>(.*?)<\/span>/is', $html, $senderMatch) === 1) {
            $sender = self::htmlToPlainText($senderMatch[1]);

            return filled($sender) ? $sender : null;
        }

        $sender = self::htmlToPlainText($html);

        return filled($sender) ? $sender : null;
    }
}
