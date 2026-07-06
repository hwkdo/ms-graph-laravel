<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
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
            'message', 'mention' => $this->handleMessage($activity, $conversationRef),
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
    private function handleMessage(array $activity, array $conversationRef): void
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

        $messageText = TeamsMemberId::normalizeMessageText($activity);

        Log::info('Teams Bot Nachricht vom Benutzer empfangen', [
            'from_id' => $fromId,
            'text' => $messageText,
        ]);

        $reply = TeamsMemberId::isHiCommand($messageText)
            ? config('ms-graph-laravel.teams_bot.hi_reply_message')
            : config('ms-graph-laravel.teams_bot.auto_reply_message');

        if (is_string($reply) && $reply !== '') {
            $this->messagingService->replyToWebhookMessage($activity, $conversationRef, $reply);
        }
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
}
