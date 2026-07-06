<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotMessageJob;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Services\TeamsBotConversationResolver;
use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

it('dispatches send teams bot message job', function (): void {
    Queue::fake();

    app(TeamsBotMessagingService::class)->queueMessage('azure-user-1', 'Hallo Test');

    Queue::assertPushed(SendTeamsBotMessageJob::class, function (SendTeamsBotMessageJob $job): bool {
        return $job->azureUserId === 'azure-user-1' && $job->text === 'Hallo Test';
    });
});

it('reports failed conversation details when messaging is not possible', function (): void {
    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-failed',
        'status' => TeamsBotConversationStatus::Failed,
        'last_error' => '[Forbidden] App is blocked by app permission policy.',
    ]);

    expect(fn () => app(TeamsBotMessagingService::class)->sendMessageSync('azure-user-failed', 'Test'))
        ->toThrow(RuntimeException::class, 'App is blocked by app permission policy');
});

it('tries to resolve a pending conversation before sending', function (): void {
    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-pending',
        'status' => TeamsBotConversationStatus::Pending,
        'installed_at' => now(),
    ]);

    $resolver = Mockery::mock(TeamsBotConversationResolver::class);
    $resolver->shouldReceive('needsResolution')->once()->andReturn(true);
    $resolver->shouldReceive('resolve')->once()->andReturnUsing(function (TeamsBotConversation $conversation): bool {
        $conversation->markActive('conv-resolved', 'https://smba.trafficmanager.net/teams/', 'tenant-1');

        return true;
    });
    $resolver->shouldReceive('ensureSdkConversationRegistered')->once();
    app()->instance(TeamsBotConversationResolver::class, $resolver);

    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')->never();
    $sdkClient->shouldReceive('registerConversation')->never();
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'text' => 'Testnachricht',
            'conversationId' => 'conv-resolved',
        ])
        ->andReturn(['messageId' => 'activity-1', 'conversationId' => 'conv-resolved']);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(TeamsBotMessagingService::class)->sendMessageSync('azure-user-pending', 'Testnachricht');

    $conversation = TeamsBotConversation::query()->where('azure_user_id', 'azure-user-pending')->first();

    expect($conversation?->status)->toBe(TeamsBotConversationStatus::Active)
        ->and($conversation?->last_message_at)->not->toBeNull();
});

it('sends a message via teams-sdk-rest when conversation is active', function (): void {
    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-active',
        'conversation_id' => 'conv-1',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')
        ->twice()
        ->andReturn(null);
    $sdkClient->shouldReceive('registerConversation')->once();
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'text' => 'Testnachricht',
            'conversationId' => 'conv-1',
        ])
        ->andReturn(['messageId' => 'activity-1', 'conversationId' => 'conv-1']);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(TeamsBotMessagingService::class)->sendMessageSync('azure-user-active', 'Testnachricht');

    $conversation = TeamsBotConversation::query()->where('azure_user_id', 'azure-user-active')->first();

    expect($conversation?->last_message_at)->not->toBeNull();
});

it('stores conversation on install.add webhook', function (): void {
    $payload = [
        'event' => 'install.add',
        'timestamp' => now()->toIso8601String(),
        'activity' => [
            'from' => [
                'id' => '29:102bd2b59-d49b-44ce-a709-580a54e1eaf8',
                'aadObjectId' => '02bd2b59-d49b-44ce-a709-580a54e1eaf8',
                'userPrincipalName' => 'max@example.com',
                'name' => 'Max Mustermann',
            ],
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
            'conversation' => [
                'id' => 'conv-webhook-1',
                'conversationType' => 'personal',
            ],
        ],
        'conversationRef' => [
            'conversationId' => 'conv-webhook-1',
            'userAadId' => '02bd2b59-d49b-44ce-a709-580a54e1eaf8',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
            'tenantId' => 'tenant-1',
        ],
    ];

    $response = $this->postJson(
        route('ms-graph-laravel.teams-webhook'),
        $payload,
        ['X-Teams-Event' => 'install.add'],
    );

    $response->assertNoContent();

    $conversation = TeamsBotConversation::query()->where('azure_user_id', '02bd2b59-d49b-44ce-a709-580a54e1eaf8')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->status)->toBe(TeamsBotConversationStatus::Active)
        ->and($conversation->conversation_id)->toBe('conv-webhook-1')
        ->and($conversation->upn)->toBe('max@example.com');
});

it('replies to hi command from webhook', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'conversationId' => 'conv-hi-1',
            'text' => config('ms-graph-laravel.teams_bot.hi_reply_message'),
        ])
        ->andReturn(['messageId' => 'activity-out-1', 'conversationId' => 'conv-hi-1']);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    $response = $this->postJson(
        route('ms-graph-laravel.teams-webhook'),
        [
            'event' => 'message',
            'activity' => [
                'type' => 'message',
                'id' => 'activity-in-1',
                'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
                'conversation' => ['id' => 'conv-hi-1'],
                'from' => [
                    'id' => '29:1azure-human-2',
                    'aadObjectId' => 'azure-human-2',
                ],
                'text' => 'Hi',
            ],
            'conversationRef' => [
                'conversationId' => 'conv-hi-1',
                'userAadId' => 'azure-human-2',
            ],
        ],
        ['X-Teams-Event' => 'message'],
    );

    $response->assertNoContent();
});

it('rejects webhook with invalid signature', function (): void {
    config()->set('ms-graph-laravel.teams_sdk_rest.webhook_secret', 'test-secret');

    $response = $this->postJson(
        route('ms-graph-laravel.teams-webhook'),
        ['event' => 'message', 'activity' => []],
        [
            'X-Teams-Event' => 'message',
            'X-Teams-Signature' => 'sha256=invalid',
        ],
    );

    $response->assertForbidden();
});

it('accepts webhook with valid signature', function (): void {
    config()->set('ms-graph-laravel.teams_sdk_rest.webhook_secret', 'test-secret');

    $body = json_encode([
        'event' => 'install.remove',
        'conversationRef' => ['userAadId' => 'azure-user-remove'],
    ], JSON_THROW_ON_ERROR);

    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-remove',
        'status' => TeamsBotConversationStatus::Active,
        'conversation_id' => 'conv-remove',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
    ]);

    $response = $this->call(
        'POST',
        route('ms-graph-laravel.teams-webhook'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TEAMS_EVENT' => 'install.remove',
            'HTTP_X_TEAMS_SIGNATURE' => $signature,
        ],
        $body,
    );

    $response->assertNoContent();

    expect(TeamsBotConversation::query()->where('azure_user_id', 'azure-user-remove')->first()?->status)
        ->toBe(TeamsBotConversationStatus::Uninstalled);
});

it('returns not found when teams bot is disabled', function (): void {
    config()->set('ms-graph-laravel.teams_bot.enabled', false);

    $response = $this->postJson(route('ms-graph-laravel.teams-webhook'), ['event' => 'message']);

    $response->assertNotFound();
});
