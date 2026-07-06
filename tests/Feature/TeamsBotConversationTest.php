<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Hwkdo\MsGraphLaravel\Models\TeamsBotConversation;

it('marks a conversation as active with connector details', function (): void {
    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-1',
        'upn' => 'user@example.com',
        'display_name' => 'Test User',
        'status' => TeamsBotConversationStatus::Pending,
    ]);

    $conversation->markActive(
        'conversation-123',
        'https://smba.trafficmanager.net/teams/',
        'tenant-abc',
    );

    $conversation->refresh();

    expect($conversation->status)->toBe(TeamsBotConversationStatus::Active)
        ->and($conversation->conversation_id)->toBe('conversation-123')
        ->and($conversation->service_url)->toBe('https://smba.trafficmanager.net/teams/')
        ->and($conversation->tenant_id)->toBe('tenant-abc')
        ->and($conversation->installed_at)->not->toBeNull()
        ->and($conversation->isReadyForMessaging())->toBeTrue();
});

it('marks a conversation as failed with error text', function (): void {
    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-2',
        'status' => TeamsBotConversationStatus::Pending,
    ]);

    $conversation->markFailed('Graph API error');

    $conversation->refresh();

    expect($conversation->status)->toBe(TeamsBotConversationStatus::Failed)
        ->and($conversation->last_error)->toBe('Graph API error')
        ->and($conversation->isReadyForMessaging())->toBeFalse();
});

it('updates last message timestamp when message was sent', function (): void {
    $conversation = TeamsBotConversation::query()->create([
        'azure_user_id' => 'azure-user-3',
        'conversation_id' => 'conversation-456',
        'service_url' => 'https://smba.trafficmanager.net/teams/',
        'status' => TeamsBotConversationStatus::Active,
    ]);

    $conversation->markMessageSent();

    $conversation->refresh();

    expect($conversation->last_message_at)->not->toBeNull()
        ->and($conversation->last_error)->toBeNull();
});
