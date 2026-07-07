<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotChannelMessageJob;
use Hwkdo\MsGraphLaravel\Jobs\SendTeamsBotChatMessageJob;
use Hwkdo\MsGraphLaravel\Services\TeamsBotChannelDirectoryService;
use Hwkdo\MsGraphLaravel\Services\TeamsBotMessagingService;
use Illuminate\Support\Facades\Queue;
use Microsoft\Graph\GraphServiceClient;

function fakeGraphTeamEntity(string $id, string $displayName): object
{
    return new class($id, $displayName)
    {
        public function __construct(private string $id, private string $displayName) {}

        public function getId(): string
        {
            return $this->id;
        }

        public function getDisplayName(): string
        {
            return $this->displayName;
        }
    };
}

function fakeGraphChatMember(?string $displayName): object
{
    return new class($displayName)
    {
        public function __construct(private ?string $displayName) {}

        public function getDisplayName(): ?string
        {
            return $this->displayName;
        }
    };
}

/**
 * @param  array<int, object>  $members
 */
function fakeGraphChatEntity(string $id, ?string $topic, array $members = []): object
{
    return new class($id, $topic, $members)
    {
        /**
         * @param  array<int, object>  $members
         */
        public function __construct(private string $id, private ?string $topic, private array $members) {}

        public function getId(): string
        {
            return $this->id;
        }

        public function getTopic(): ?string
        {
            return $this->topic;
        }

        /**
         * @return array<int, object>
         */
        public function getMembers(): array
        {
            return $this->members;
        }
    };
}

it('returns empty team search results for short queries without hitting graph', function (): void {
    $graph = Mockery::mock(GraphServiceClient::class);
    $graph->shouldNotReceive('teams');

    $service = new TeamsBotChannelDirectoryService($graph);

    expect($service->searchTeams('a'))->toBe([]);
});

it('searches teams via microsoft graph prefix filter', function (): void {
    $collection = Mockery::mock();
    $collection->shouldReceive('getValue')->andReturn([
        fakeGraphTeamEntity('team-1', 'Marketing'),
        fakeGraphTeamEntity('team-2', 'Marketing DACH'),
    ]);

    $promise = Mockery::mock();
    $promise->shouldReceive('wait')->andReturn($collection);

    $teamsBuilder = Mockery::mock();
    $teamsBuilder->shouldReceive('get')->once()->andReturn($promise);

    $graph = Mockery::mock(GraphServiceClient::class);
    $graph->shouldReceive('teams')->once()->andReturn($teamsBuilder);

    $service = new TeamsBotChannelDirectoryService($graph);

    expect($service->searchTeams('marketing'))->toBe([
        ['teamId' => 'team-1', 'teamName' => 'Marketing'],
        ['teamId' => 'team-2', 'teamName' => 'Marketing DACH'],
    ]);
});

it('lists channels of a team via microsoft graph', function (): void {
    $collection = Mockery::mock();
    $collection->shouldReceive('getValue')->andReturn([
        fakeGraphTeamEntity('channel-1', 'General'),
        fakeGraphTeamEntity('channel-2', 'Ankündigungen'),
    ]);

    $promise = Mockery::mock();
    $promise->shouldReceive('wait')->andReturn($collection);

    $channelsBuilder = Mockery::mock();
    $channelsBuilder->shouldReceive('get')->once()->andReturn($promise);

    $teamItemBuilder = Mockery::mock();
    $teamItemBuilder->shouldReceive('channels')->once()->andReturn($channelsBuilder);

    $teamsBuilder = Mockery::mock();
    $teamsBuilder->shouldReceive('byTeamId')->once()->with('team-1')->andReturn($teamItemBuilder);

    $graph = Mockery::mock(GraphServiceClient::class);
    $graph->shouldReceive('teams')->once()->andReturn($teamsBuilder);

    $service = new TeamsBotChannelDirectoryService($graph);

    expect($service->listChannels('team-1'))->toBe([
        ['channelId' => 'channel-1', 'channelName' => 'General'],
        ['channelId' => 'channel-2', 'channelName' => 'Ankündigungen'],
    ]);
});

it('returns empty group chat results for empty user id without hitting graph', function (): void {
    $graph = Mockery::mock(GraphServiceClient::class);
    $graph->shouldNotReceive('users');

    $service = new TeamsBotChannelDirectoryService($graph);

    expect($service->listUserGroupChats(''))->toBe([]);
});

it('lists group chats of a user via microsoft graph with topic and member fallback labels', function (): void {
    $collection = Mockery::mock();
    $collection->shouldReceive('getValue')->andReturn([
        fakeGraphChatEntity('19:chat-1@thread.v2', 'Projekt Alpha'),
        fakeGraphChatEntity('19:chat-2@thread.v2', null, [
            fakeGraphChatMember('Max Mustermann'),
            fakeGraphChatMember('Erika Beispiel'),
        ]),
    ]);

    $promise = Mockery::mock();
    $promise->shouldReceive('wait')->andReturn($collection);

    $chatsBuilder = Mockery::mock();
    $chatsBuilder->shouldReceive('get')->once()->andReturn($promise);

    $userItemBuilder = Mockery::mock();
    $userItemBuilder->shouldReceive('chats')->once()->andReturn($chatsBuilder);

    $usersBuilder = Mockery::mock();
    $usersBuilder->shouldReceive('byUserId')->once()->with('azure-123')->andReturn($userItemBuilder);

    $graph = Mockery::mock(GraphServiceClient::class);
    $graph->shouldReceive('users')->once()->andReturn($usersBuilder);

    $service = new TeamsBotChannelDirectoryService($graph);

    expect($service->listUserGroupChats('azure-123'))->toBe([
        ['chatId' => '19:chat-1@thread.v2', 'label' => 'Projekt Alpha'],
        ['chatId' => '19:chat-2@thread.v2', 'label' => 'Max Mustermann, Erika Beispiel'],
    ]);
});

it('dispatches send teams bot chat message job', function (): void {
    Queue::fake();

    app(TeamsBotMessagingService::class)->queueChatMessage('19:chat-1@thread.v2', 'Hallo Gruppenchat');

    Queue::assertPushed(SendTeamsBotChatMessageJob::class, function (SendTeamsBotChatMessageJob $job): bool {
        return $job->chatId === '19:chat-1@thread.v2'
            && $job->text === 'Hallo Gruppenchat';
    });
});

it('sends a teams bot chat message via sdk rest', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'conversationId' => '19:chat-1@thread.v2',
            'text' => 'Gruppenchat-Test',
        ])
        ->andReturn([
            'messageId' => 'activity-2',
            'conversationId' => '19:chat-1@thread.v2',
        ]);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(TeamsBotMessagingService::class)->sendChatMessageSync('19:chat-1@thread.v2', 'Gruppenchat-Test');
});

it('dispatches send teams bot channel message job', function (): void {
    Queue::fake();

    app(TeamsBotMessagingService::class)->queueChannelMessage('team-1', 'channel-1', 'Hallo Kanal');

    Queue::assertPushed(SendTeamsBotChannelMessageJob::class, function (SendTeamsBotChannelMessageJob $job): bool {
        return $job->teamId === 'team-1'
            && $job->channelId === 'channel-1'
            && $job->text === 'Hallo Kanal';
    });
});

it('sends a teams bot channel message via sdk rest', function (): void {
    $sdkClient = Mockery::mock(TeamsSdkRestClient::class);
    $sdkClient->shouldReceive('sendMessage')
        ->once()
        ->with([
            'teamId' => 'team-1',
            'channelId' => 'channel-1',
            'text' => 'Kanal-Test',
        ])
        ->andReturn([
            'messageId' => 'activity-1',
            'conversationId' => 'conv-channel-1',
        ]);
    app()->instance(TeamsSdkRestClient::class, $sdkClient);

    app(TeamsBotMessagingService::class)->sendChannelMessageSync('team-1', 'channel-1', 'Kanal-Test');
});
