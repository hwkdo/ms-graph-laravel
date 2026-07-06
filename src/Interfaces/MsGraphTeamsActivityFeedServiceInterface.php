<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphTeamsActivityFeedServiceInterface
{
    public function isEnabled(): bool;

    public function sendNotification(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
    ): void;

    public function sendNotificationSync(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
    ): void;
}
