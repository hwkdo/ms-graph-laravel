<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsActivityFeedServiceInterface;

class TeamsActivityFeedService implements MsGraphTeamsActivityFeedServiceInterface
{
    public function __construct(
        private readonly TeamsActivityFeedNotificationService $notificationService,
    ) {}

    public function isEnabled(): bool
    {
        if (! config('ms-graph-laravel.teams_activity_feed.enabled')) {
            return false;
        }

        $registration = (string) config(
            'ms-graph-laravel.teams_activity_feed.graph_registration',
            'teams_bot',
        );

        $clientId = config('ms-graph-laravel.azure_app_registrations.'.$registration.'.client_id');
        $clientSecret = config('ms-graph-laravel.azure_app_registrations.'.$registration.'.client_secret');
        $teamsAppId = config('ms-graph-laravel.teams_bot.teams_app_id');

        return filled($clientId)
            && filled($clientSecret)
            && filled($teamsAppId);
    }

    public function sendNotification(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
    ): void {
        $this->notificationService->queueNotification(
            $azureUserId,
            $previewText,
            $actorText,
            $topicTitle,
            $webUrl,
        );
    }

    public function sendNotificationSync(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
    ): void {
        $this->notificationService->sendNotificationSync(
            $azureUserId,
            $previewText,
            $actorText,
            $topicTitle,
            $webUrl,
        );
    }
}
