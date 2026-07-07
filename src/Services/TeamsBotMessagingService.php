<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotChannelMessageJob;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotChatMessageJob;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotMessageJob;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TeamsBotMessagingService
{
    public function __construct(
        private readonly TeamsSdkRestClient $sdkClient,
        private readonly TeamsBotConversationResolver $conversationResolver,
    ) {}

    public function queueMessage(string $azureUserId, string $text): void
    {
        SendTeamsBotMessageJob::dispatch($azureUserId, $text);
    }

    public function queueChannelMessage(string $teamId, string $channelId, string $text): void
    {
        SendTeamsBotChannelMessageJob::dispatch($teamId, $channelId, $text);
    }

    public function sendChannelMessageSync(string $teamId, string $channelId, string $text): void
    {
        try {
            $this->sdkClient->sendMessage([
                'teamId' => $teamId,
                'channelId' => $channelId,
                'text' => $text,
            ]);
        } catch (Throwable $exception) {
            Log::error('Teams Bot Kanal-Nachricht fehlgeschlagen', [
                'team_id' => $teamId,
                'channel_id' => $channelId,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    public function queueChatMessage(string $chatId, string $text): void
    {
        SendTeamsBotChatMessageJob::dispatch($chatId, $text);
    }

    public function sendChatMessageSync(string $chatId, string $text): void
    {
        try {
            $this->sdkClient->sendMessage([
                'conversationId' => $chatId,
                'text' => $text,
            ]);
        } catch (Throwable $exception) {
            Log::error('Teams Bot Gruppenchat-Nachricht fehlgeschlagen', [
                'chat_id' => $chatId,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    public function sendMessageSync(string $azureUserId, string $text): void
    {
        $conversation = TeamsBotConversation::query()
            ->where('azure_user_id', $azureUserId)
            ->first();

        if ($conversation === null) {
            throw new RuntimeException('Für diesen Benutzer ist kein Teams-Bot installiert.');
        }

        if ($conversation->status === TeamsBotConversationStatus::Failed) {
            throw new RuntimeException($this->buildNotReadyMessage($conversation));
        }

        if ($this->conversationResolver->needsResolution($conversation)) {
            $this->conversationResolver->resolve($conversation);
            $conversation->refresh();
        }

        if (! $conversation->isReadyForMessaging()) {
            throw new RuntimeException($this->buildNotReadyMessage($conversation));
        }

        $this->conversationResolver->ensureSdkConversationRegistered($conversation);

        try {
            $result = $this->sdkClient->sendMessage(
                $this->buildSendPayload($conversation, $azureUserId, $text),
            );

            $this->syncConversationAfterSend($conversation, $result['conversationId']);
            $conversation->markMessageSent();
        } catch (Throwable $exception) {
            $conversation->markFailed($exception->getMessage());

            Log::error('Teams Bot Nachricht fehlgeschlagen', [
                'azure_user_id' => $azureUserId,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    public function replyToWebhookMessage(array $activity, array $conversationRef, string $text): void
    {
        $conversationId = $conversationRef['conversationId']
            ?? (is_array($activity['conversation'] ?? null) ? ($activity['conversation']['id'] ?? null) : null);
        $messageId = $activity['id'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            Log::warning('Teams Bot Auto-Antwort übersprungen (conversationId fehlt)', [
                'activity_id' => $activity['id'] ?? null,
            ]);

            return;
        }

        try {
            $this->sdkClient->sendMessage([
                'conversationId' => $this->normalizeConversationId($conversationId),
                'text' => $text,
            ]);

            Log::info('Teams Bot Auto-Antwort gesendet', [
                'conversation_id' => $conversationId,
                'activity_id' => $messageId,
            ]);
        } catch (Throwable $exception) {
            Log::error('Teams Bot Auto-Antwort fehlgeschlagen', [
                'conversation_id' => $conversationId,
                'activity_id' => $messageId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeConversationId(string $conversationId): string
    {
        $parts = explode(';messageid=', $conversationId, 2);

        return $parts[0] !== '' ? $parts[0] : $conversationId;
    }

    private function syncConversationAfterSend(TeamsBotConversation $conversation, string $conversationId): void
    {
        if ($conversation->conversation_id === $conversationId && $conversation->isReadyForMessaging()) {
            return;
        }

        $stored = $this->sdkClient->getConversationForUser($conversation->azure_user_id);
        $serviceUrl = is_array($stored) && is_string($stored['serviceUrl'] ?? null)
            ? $stored['serviceUrl']
            : $conversation->service_url;
        $tenantId = is_array($stored) && is_string($stored['tenantId'] ?? null)
            ? $stored['tenantId']
            : $conversation->tenant_id;

        if (! is_string($serviceUrl) || $serviceUrl === '') {
            $conversation->update([
                'conversation_id' => $conversationId,
                'status' => TeamsBotConversationStatus::Active,
            ]);

            return;
        }

        $conversation->markActive($conversationId, $serviceUrl, $tenantId);
    }

    /**
     * @return array<string, string>
     */
    private function buildSendPayload(TeamsBotConversation $conversation, string $azureUserId, string $text): array
    {
        $payload = ['text' => $text];

        if (filled($conversation->conversation_id)) {
            $payload['conversationId'] = $conversation->conversation_id;
        } else {
            $payload['userAadId'] = $azureUserId;
        }

        return $payload;
    }

    private function buildNotReadyMessage(TeamsBotConversation $conversation): string
    {
        if ($conversation->status === TeamsBotConversationStatus::Failed && filled($conversation->last_error)) {
            return 'Teams-Bot ist fehlgeschlagen: '.$conversation->last_error;
        }

        if ($conversation->status === TeamsBotConversationStatus::Pending) {
            return 'Teams-Bot ist installiert, aber die Conversation ist noch nicht bereit. '
                .'Bitte Bot-Installation erneut ausführen oder kurz warten, bis der Webhook eintrifft.';
        }

        if ($conversation->status === TeamsBotConversationStatus::Uninstalled) {
            return 'Teams-Bot wurde für diesen Benutzer deinstalliert.';
        }

        if ($conversation->status === TeamsBotConversationStatus::Active) {
            return 'Teams-Bot ist aktiv, aber die Conversation ist im teams-sdk-rest Service noch nicht verfügbar. '
                .'Bitte kurz warten oder Bot-Installation erneut ausführen.';
        }

        return 'Teams-Bot ist für diesen Benutzer noch nicht bereit (Status: '.$conversation->status->value.').';
    }
}
