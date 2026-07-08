<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Data\TeamsBotIncomingMessage;
use Hwkdo\MsGraphLaravel\Events\TeamsBotMessageReceived;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

function teamsWebhook(array $payload, string $event): TestResponse
{
    return test()->postJson(
        route('ms-graph-laravel.teams-webhook'),
        $payload + ['event' => $event],
        ['X-Teams-Event' => $event],
    );
}

it('dispatches the teams bot message event for direct messages', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-dm',
            'conversation' => ['id' => 'conv-dm', 'conversationType' => 'personal'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-dm'],
            'text' => 'erstelle mir ein Ticket, dass der Drucker kaputt ist',
        ],
        'conversationRef' => ['conversationId' => 'conv-dm', 'userAadId' => 'azure-dm'],
    ], 'message')->assertNoContent();

    Event::assertDispatched(TeamsBotMessageReceived::class, function (TeamsBotMessageReceived $event): bool {
        return $event->message->isDirectMessage()
            && str_contains($event->message->text, 'Drucker');
    });
});

it('ignores non-mention channel messages', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-channel',
            'conversation' => ['id' => 'conv-channel', 'conversationType' => 'channel'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-channel'],
            'text' => 'nur ein normaler Kanalbeitrag',
        ],
        'conversationRef' => [
            'conversationId' => 'conv-channel',
            'userAadId' => 'azure-channel',
            'teamId' => 'team-1',
            'channelId' => 'channel-1',
        ],
    ], 'message')->assertNoContent();

    Event::assertNotDispatched(TeamsBotMessageReceived::class);
});

it('dispatches the event for channel mentions via message event', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-mention',
            'conversation' => ['id' => 'conv-channel', 'conversationType' => 'channel'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-channel'],
            'text' => '<at>Bot</at> erstelle ein Ticket: Licht defekt',
        ],
        'conversationRef' => [
            'conversationId' => 'conv-channel',
            'userAadId' => 'azure-channel',
            'teamId' => 'team-1',
            'channelId' => 'channel-1',
        ],
    ], 'message')->assertNoContent();

    Event::assertDispatched(TeamsBotMessageReceived::class, function (TeamsBotMessageReceived $event): bool {
        return $event->message->isChannel() && $event->message->isMention;
    });
});

it('ignores the duplicate mention event', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-mention',
            'conversation' => ['id' => 'conv-channel', 'conversationType' => 'channel'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-channel'],
            'text' => '<at>Bot</at> Ticket: Test',
        ],
        'conversationRef' => [
            'conversationId' => 'conv-channel',
            'userAadId' => 'azure-channel',
            'teamId' => 'team-1',
            'channelId' => 'channel-1',
        ],
    ], 'mention')->assertNoContent();

    Event::assertNotDispatched(TeamsBotMessageReceived::class);
});

it('dispatches the event for group chat mentions via message event', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-group',
            'conversation' => ['id' => '19:abc@thread.v2'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-group'],
            'text' => '<at>Bot</at> erstelle ein Ticket: Wäsche erledigen',
        ],
        'conversationRef' => [
            'conversationId' => '19:abc@thread.v2',
            'userAadId' => 'azure-group',
        ],
    ], 'message')->assertNoContent();

    Event::assertDispatched(TeamsBotMessageReceived::class, function (TeamsBotMessageReceived $event): bool {
        return $event->message->isGroupChat() && $event->message->isMention;
    });
});

it('ignores non-mention group chat messages', function (): void {
    Event::fake([TeamsBotMessageReceived::class]);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-group',
            'conversation' => ['id' => '19:abc@thread.v2'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-group'],
            'text' => 'nur ein normaler Gruppenchat-Beitrag',
        ],
        'conversationRef' => [
            'conversationId' => '19:abc@thread.v2',
            'userAadId' => 'azure-group',
        ],
    ], 'message')->assertNoContent();

    Event::assertNotDispatched(TeamsBotMessageReceived::class);
});

it('sends the acknowledgement returned by a listener', function (): void {
    Event::listen(TeamsBotMessageReceived::class, fn (TeamsBotMessageReceived $event): string => 'Ticket wird erstellt.');

    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'conversationId' => 'conv-dm',
            'text' => 'Ticket wird erstellt.',
        ])
        ->andReturn(['messageId' => 'out-1', 'conversationId' => 'conv-dm']);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    teamsWebhook([
        'activity' => [
            'type' => 'message',
            'id' => 'activity-dm',
            'conversation' => ['id' => 'conv-dm', 'conversationType' => 'personal'],
            'from' => ['id' => '29:1human', 'aadObjectId' => 'azure-dm'],
            'text' => 'erstelle ein Ticket: Test',
        ],
        'conversationRef' => ['conversationId' => 'conv-dm', 'userAadId' => 'azure-dm'],
    ], 'message')->assertNoContent();
});

it('builds a group chat incoming message from thread v2 conversation id', function (): void {
    $message = TeamsBotIncomingMessage::fromWebhook(
        'message',
        [
            'text' => '<at>Bot</at> Hallo',
            'conversation' => ['id' => '19:abc@thread.v2'],
            'from' => ['userPrincipalName' => 'max@example.com', 'name' => 'Max'],
            'id' => 'msg-1',
        ],
        ['conversationId' => '19:abc@thread.v2'],
        'azure-x',
    );

    expect($message->isGroupChat())->toBeTrue()
        ->and($message->isMention)->toBeTrue()
        ->and($message->text)->toBe('Hallo');
});

it('builds a channel incoming message from a webhook payload', function (): void {
    $message = TeamsBotIncomingMessage::fromWebhook(
        'mention',
        [
            'text' => '<at>Bot</at> Hallo',
            'conversation' => ['id' => 'conv-x', 'conversationType' => 'channel'],
            'from' => ['userPrincipalName' => 'max@example.com', 'name' => 'Max'],
            'id' => 'msg-1',
        ],
        ['conversationId' => 'conv-x', 'teamId' => 'team-1', 'channelId' => 'channel-1'],
        'azure-x',
    );

    expect($message->isChannel())->toBeTrue()
        ->and($message->isMention)->toBeTrue()
        ->and($message->text)->toBe('Hallo')
        ->and($message->upn)->toBe('max@example.com')
        ->and($message->teamId)->toBe('team-1');
});
