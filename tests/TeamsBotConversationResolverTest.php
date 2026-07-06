<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;
use Hwkdo\MsGraphLaravel\Services\TeamsBotConversationResolver;
use Hwkdo\MsGraphLaravel\Services\TeamsBotInstallationService;

it('clears stored conversation fields when finalizing installation', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')
        ->once()
        ->andReturn([
            'conversationId' => 'a:bot-framework-conversation-id',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
            'tenantId' => 'tenant-1',
        ]);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-install-clear',
        'conversation_id' => '19:user-id_bot-id@unq.gbl.spaces',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
        'status' => TeamsBotConversationStatus::Active,
        'installed_at' => now()->subDay(),
    ]);

    $method = new ReflectionMethod(TeamsBotInstallationService::class, 'finalizeInstallation');
    $method->invoke(
        app(TeamsBotInstallationService::class),
        $conversation,
        'azure-user-install-clear',
        'user@example.com',
    );

    $conversation->refresh();

    expect($conversation->conversation_id)->toBe('a:bot-framework-conversation-id')
        ->and($conversation->status)->toBe(TeamsBotConversationStatus::Active);
});

it('resolves a pending conversation via teams-sdk-rest', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')
        ->once()
        ->with('azure-user-resolver')
        ->andReturn([
            'conversationId' => 'a:bot-framework-conversation-id',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
            'tenantId' => 'tenant-1',
        ]);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-resolver',
        'status' => TeamsBotConversationStatus::Pending,
        'installed_at' => now(),
    ]);

    $resolved = app(TeamsBotConversationResolver::class)->resolve($conversation);

    $conversation->refresh();

    expect($resolved)->toBeTrue()
        ->and($conversation->conversation_id)->toBe('a:bot-framework-conversation-id')
        ->and($conversation->status)->toBe(TeamsBotConversationStatus::Active);
});

it('keeps ready conversations without re-resolving from teams-sdk-rest', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldNotReceive('getConversationForUser');
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-bot-conv',
        'conversation_id' => 'a:existing-bot-framework-id',
        'service_url' => 'https://smba.trafficmanager.net/emea/',
        'status' => TeamsBotConversationStatus::Active,
        'installed_at' => now(),
    ]);

    $resolved = app(TeamsBotConversationResolver::class)->resolve($conversation);

    $conversation->refresh();

    expect($resolved)->toBeTrue()
        ->and($conversation->conversation_id)->toBe('a:existing-bot-framework-id');
});

it('detects when a conversation still needs resolution', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')
        ->once()
        ->with('azure-user-needs-ready')
        ->andReturn(null);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    $resolver = app(TeamsBotConversationResolver::class);

    $pending = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-needs-pending',
        'status' => TeamsBotConversationStatus::Pending,
    ]);

    $activeWithoutFields = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-needs-active-empty',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    $activeReady = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-needs-ready',
        'conversation_id' => 'a:existing-bot-framework-id',
        'service_url' => 'https://smba.trafficmanager.net/emea/',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    expect($resolver->needsResolution($pending))->toBeTrue()
        ->and($resolver->needsResolution($activeWithoutFields))->toBeTrue()
        ->and($resolver->needsResolution($activeReady))->toBeTrue();
});

it('registers mysql conversation in teams-sdk-rest before sending', function (): void {
    TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-active',
        'conversation_id' => 'conv-1',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('getConversationForUser')
        ->twice()
        ->with('azure-user-active')
        ->andReturn(null);
    $sdkClient->shouldReceive('registerConversation')
        ->once()
        ->with([
            'userAadId' => 'azure-user-active',
            'conversationId' => 'conv-1',
            'serviceUrl' => 'https://smba.trafficmanager.net/teams/',
        ]);
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'text' => 'Testnachricht',
            'conversationId' => 'conv-1',
        ])
        ->andReturn(['messageId' => 'activity-1', 'conversationId' => 'conv-1']);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(\Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService::class)
        ->sendMessageSync('azure-user-active', 'Testnachricht');
});
