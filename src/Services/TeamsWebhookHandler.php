<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Data\TeamsBotIncomingMessage;
use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Events\TeamsBotMessageReceived;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Support\TeamsMemberId;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeamsWebhookHandler
{
    public function __construct(
        private readonly TeamsBotMessagingService $messagingService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $activity = is_array($payload['activity'] ?? null) ? $payload['activity'] : [];
        $conversationRef = is_array($payload['conversationRef'] ?? null) ? $payload['conversationRef'] : [];

        match ($event) {
            'install.add', 'conversationUpdate.channelMemberAdded' => $this->handleInstallAdd($activity, $conversationRef),
            'install.remove' => $this->handleInstallRemove($conversationRef),
            'message', 'mention' => $this->handleMessage($event, $activity, $conversationRef),
            default => Log::debug('Teams Webhook Event ignoriert', ['event' => $event]),
        };
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    private function handleInstallAdd(array $activity, array $conversationRef): void
    {
        $azureUserId = $this->resolveAzureUserId($activity, $conversationRef);

        if ($azureUserId === null) {
            return;
        }

        $conversation = TeamsBotConversation::query()
            ->where('azure_user_id', $azureUserId)
            ->first();

        if ($conversation === null) {
            $conversation = TeamsBotConversation::query()->create([
                'azure_user_id' => $azureUserId,
                'upn' => $this->resolveUpn($activity),
                'display_name' => $this->resolveDisplayName($activity),
                'status' => TeamsBotConversationStatus::Pending,
            ]);
        }

        $this->syncConversationFromRef($conversation, $conversationRef, $activity);

        Log::info('Teams Bot Conversation aktiviert', [
            'azure_user_id' => $azureUserId,
            'conversation_id' => $conversation->fresh()?->conversation_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $conversationRef
     */
    private function handleInstallRemove(array $conversationRef): void
    {
        $azureUserId = $conversationRef['userAadId'] ?? null;

        if (! is_string($azureUserId) || $azureUserId === '') {
            return;
        }

        TeamsBotConversation::query()
            ->where('azure_user_id', strtolower($azureUserId))
            ->update([
                'status' => TeamsBotConversationStatus::Uninstalled,
                'last_error' => null,
            ]);
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    private function handleMessage(string $event, array $activity, array $conversationRef): void
    {
        $fromId = $activity['from']['id'] ?? null;
        $botAppId = config('ms-graph-laravel.teams_bot.app_id');

        if (! is_string($fromId) || $this->isFromBot($fromId, is_string($botAppId) ? $botAppId : null)) {
            Log::debug('Teams Webhook Nachricht ignoriert (vom Bot oder ohne Absender)', [
                'from_id' => $fromId,
            ]);

            return;
        }

        $azureUserId = $this->resolveAzureUserId($activity, $conversationRef);

        if ($azureUserId !== null) {
            $conversation = TeamsBotConversation::query()
                ->where('azure_user_id', $azureUserId)
                ->first();

            if ($conversation !== null) {
                $this->syncConversationFromRef($conversation, $conversationRef, $activity);
            }
        }

        $message = TeamsBotIncomingMessage::fromWebhook($event, $activity, $conversationRef, $azureUserId);

        // Das separate 'mention'-Event ist ein Duplikat des 'message'-Events (Kanal/Gruppenchat).
        // Verarbeitung erfolgt am message-Event, wenn der Bot erwähnt wurde.
        if ($event === 'mention') {
            return;
        }

        // In Kanälen und Gruppenchats nur auf @mentions reagieren.
        if (! $message->isDirectMessage() && ! $message->isMention) {
            return;
        }

        Log::info('Teams Bot Nachricht vom Benutzer empfangen', [
            'from_id' => $fromId,
            'event' => $event,
            'conversation_type' => $message->conversationType,
            'text' => $message->text,
            'quoted_text' => $message->quotedText,
            'quoted_sender_name' => $message->quotedSenderName,
            'quoted_sender_azure_id' => $message->quotedSenderAzureId,
            'quoted_message_id' => $message->quotedMessageId,
        ]);

        if (config('ms-graph-laravel.teams_sdk_rest.log_webhook_payload', false)
            || $message->hasQuotedContent()
            || $this->activityLooksLikeQuote($activity)) {
            Log::info('Teams Bot Activity Payload (Debug)', [
                'activity_id' => $message->messageId,
                'text' => $activity['text'] ?? null,
                'reply_to_id' => $activity['replyToId'] ?? null,
                'attachment_types' => collect(is_array($activity['attachments'] ?? null) ? $activity['attachments'] : [])
                    ->map(fn (mixed $attachment): ?string => is_array($attachment) ? ($attachment['contentType'] ?? null) : null)
                    ->filter()
                    ->values()
                    ->all(),
                'entity_types' => collect(is_array($activity['entities'] ?? null) ? $activity['entities'] : [])
                    ->map(fn (mixed $entity): ?string => is_array($entity) ? ($entity['type'] ?? null) : null)
                    ->filter()
                    ->values()
                    ->all(),
                'attachments' => $activity['attachments'] ?? null,
                'entities' => $activity['entities'] ?? null,
            ]);
        }

        if ($this->dispatchToProcessors($message, $activity, $conversationRef)) {
            return;
        }

        $reply = $this->resolveFallbackReply($message);

        if (is_string($reply) && $reply !== '') {
            $this->messagingService->replyToWebhookMessage($activity, $conversationRef, $reply);
        }
    }

    /**
     * Verteilt die Nachricht an registrierte Listener (z. B. Ticket-Erstellung). Gibt einen
     * Bestätigungstext zurück, wenn ein Listener die Nachricht übernommen hat.
     *
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    private function dispatchToProcessors(
        TeamsBotIncomingMessage $message,
        array $activity,
        array $conversationRef,
    ): bool {
        $responses = TeamsBotMessageReceived::dispatch($message);

        $acknowledgement = collect(is_array($responses) ? $responses : [])
            ->first(fn ($response): bool => is_string($response) && trim($response) !== '');

        if (! is_string($acknowledgement)) {
            return false;
        }

        $this->messagingService->replyToWebhookMessage($activity, $conversationRef, $acknowledgement);

        return true;
    }

    private function resolveFallbackReply(TeamsBotIncomingMessage $message): ?string
    {
        if (! $message->isDirectMessage()) {
            $help = config('ms-graph-laravel.teams_bot.mention_help_message');

            return is_string($help) ? $help : null;
        }

        $reply = TeamsMemberId::isHiCommand($message->text)
            ? config('ms-graph-laravel.teams_bot.hi_reply_message')
            : config('ms-graph-laravel.teams_bot.auto_reply_message');

        return is_string($reply) ? $reply : null;
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    private function syncConversationFromRef(
        TeamsBotConversation $conversation,
        array $conversationRef,
        array $activity,
    ): void {
        $conversationId = $conversationRef['conversationId']
            ?? (is_array($activity['conversation'] ?? null) ? ($activity['conversation']['id'] ?? null) : null);
        $serviceUrl = $conversationRef['serviceUrl'] ?? $activity['serviceUrl'] ?? null;
        $tenantId = $conversationRef['tenantId']
            ?? (is_array($activity['conversation'] ?? null) ? ($activity['conversation']['tenantId'] ?? null) : null)
            ?? (is_array($activity['channelData']['tenant'] ?? null) ? ($activity['channelData']['tenant']['id'] ?? null) : null);

        if (! is_string($conversationId) || $conversationId === '' || ! is_string($serviceUrl) || $serviceUrl === '') {
            return;
        }

        $conversation->markActive(
            $conversationId,
            $serviceUrl,
            is_string($tenantId) ? $tenantId : null,
        );

        $this->registerConversationInSdk($conversation, $conversationRef, $activity);
    }

    /**
     * @param  array<string, mixed>  $conversationRef
     * @param  array<string, mixed>  $activity
     */
    private function registerConversationInSdk(
        TeamsBotConversation $conversation,
        array $conversationRef,
        array $activity,
    ): void {
        if (! filled($conversation->conversation_id)) {
            return;
        }

        try {
            app(TeamsSdkRestClient::class)->registerConversation([
                'userAadId' => $conversation->azure_user_id,
                'conversationId' => $conversation->conversation_id,
                'serviceUrl' => $conversation->service_url
                    ?? $conversationRef['serviceUrl']
                    ?? $activity['serviceUrl']
                    ?? null,
                'tenantId' => $conversation->tenant_id
                    ?? $conversationRef['tenantId']
                    ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Teams Webhook konnte Conversation nicht in teams-sdk-rest registrieren', [
                'azure_user_id' => $conversation->azure_user_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $conversationRef
     */
    private function resolveAzureUserId(array $activity, array $conversationRef): ?string
    {
        $userAadId = $conversationRef['userAadId'] ?? $activity['from']['aadObjectId'] ?? null;

        if (is_string($userAadId) && $userAadId !== '') {
            return strtolower($userAadId);
        }

        $fromId = $activity['from']['id'] ?? null;

        if (! is_string($fromId) || $fromId === '') {
            return null;
        }

        return TeamsMemberId::resolveAzureUserId($fromId);
    }

    private function isFromBot(string $fromId, ?string $botAppId): bool
    {
        if ($botAppId === null || $botAppId === '') {
            return false;
        }

        if ($fromId === $botAppId) {
            return true;
        }

        return str_contains($fromId, $botAppId);
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function resolveUpn(array $activity): ?string
    {
        $from = is_array($activity['from'] ?? null) ? $activity['from'] : [];
        $upn = $from['userPrincipalName'] ?? $from['email'] ?? null;

        return is_string($upn) && $upn !== '' ? $upn : null;
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function resolveDisplayName(array $activity): ?string
    {
        $from = is_array($activity['from'] ?? null) ? $activity['from'] : [];
        $name = $from['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function activityLooksLikeQuote(array $activity): bool
    {
        $text = $activity['text'] ?? '';

        if (is_string($text) && str_contains($text, '<blockquote')) {
            return true;
        }

        $attachments = $activity['attachments'] ?? [];

        if (! is_array($attachments)) {
            return false;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $contentType = $attachment['contentType'] ?? null;

            if ($contentType === 'messageReference') {
                return true;
            }

            if ($contentType === 'text/html') {
                $content = $attachment['content'] ?? '';

                if (is_string($content) && str_contains($content, '<blockquote')) {
                    return true;
                }
            }
        }

        $entities = $activity['entities'] ?? [];

        if (! is_array($entities)) {
            return false;
        }

        foreach ($entities as $entity) {
            if (is_array($entity) && ($entity['type'] ?? null) === 'quotedReply') {
                return true;
            }
        }

        return filled($activity['replyToId'] ?? null);
    }
}
