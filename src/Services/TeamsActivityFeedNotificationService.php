<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsActivityFeedNotificationJob;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Throwable;

class TeamsActivityFeedNotificationService
{
    protected static GraphServiceClient $graph;

    public function __construct(
        private readonly TeamsActivityFeedNotificationBuilder $notificationBuilder,
    ) {
        $registration = (string) config(
            'ms-graph-laravel.teams_activity_feed.graph_registration',
            'teams_bot',
        );

        $client = new Client;
        self::$graph = $client($registration);
    }

    public function queueNotification(
        string $azureUserId,
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
    ): void {
        SendTeamsActivityFeedNotificationJob::dispatch(
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
        if (! config('ms-graph-laravel.teams_activity_feed.enabled')) {
            throw new RuntimeException('Teams Activity Feed ist deaktiviert.');
        }

        $body = $this->notificationBuilder->build(
            previewText: $previewText,
            actorText: $actorText,
            topicTitle: $topicTitle,
            webUrl: $webUrl,
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
