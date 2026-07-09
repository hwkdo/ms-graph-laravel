<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Throwable;

class TeamsActivityNotificationService
{
    protected static GraphServiceClient $graph;

    public function __construct(
        private readonly TeamsActivityNotificationBuilder $notificationBuilder,
        string $graphRegistration = 'teams_bot',
    ) {
        $client = new Client;
        self::$graph = $client($graphRegistration);
    }

    public function sendNotificationSync(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
        ?string $teamsAppId = null,
        ?string $activityType = null,
    ): void {
        $body = $this->notificationBuilder->build(
            previewText: $previewText,
            actorText: $actorText,
            topicTitle: $topicTitle,
            webUrl: $webUrl,
            teamsAppId: $teamsAppId,
            activityType: $activityType,
        );

        try {
            self::$graph->users()
                ->byUserId($azureUserId)
                ->teamwork()
                ->sendActivityNotification()
                ->post($body)
                ->wait();
        } catch (Throwable $exception) {
            $message = GraphExceptionMessage::resolve(
                $exception,
                'Unbekannter Fehler beim Senden der Activity-Feed-Benachrichtigung.',
            );

            Log::error('Teams Activity Feed Benachrichtigung fehlgeschlagen', [
                'azure_user_id' => $azureUserId,
                'message' => $message,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
    }
}
