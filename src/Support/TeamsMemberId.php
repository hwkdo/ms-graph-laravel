<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Support;

class TeamsMemberId
{
    public static function resolveAzureUserId(string $memberId): string
    {
        if (preg_match('/29:1([0-9a-f-]{36})/i', $memberId, $matches) === 1) {
            return strtolower($matches[1]);
        }

        if (preg_match('/^[0-9a-f-]{36}$/i', $memberId) === 1) {
            return strtolower($memberId);
        }

        return $memberId;
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    public static function normalizeMessageText(array $activity): string
    {
        return TeamsActivityContentParser::parse($activity)['text'];
    }

    public static function isHiCommand(string $text): bool
    {
        $normalized = strtolower(trim($text));

        return in_array($normalized, ['hi', 'hello', 'hallo'], true);
    }
}
