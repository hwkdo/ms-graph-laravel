<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Illuminate\Support\Facades\Http;

it('sends messages via teams-sdk-rest api', function (): void {
    Http::fake([
        'http://teams-sdk-rest.test/v1/messages' => Http::response([
            'success' => true,
            'messageId' => 'msg-1',
            'conversationId' => 'conv-1',
        ]),
    ]);

    $result = app(TeamsSdkRestClient::class)->sendMessage([
        'userAadId' => 'azure-user-1',
        'text' => 'Hallo',
    ]);

    expect($result)->toBe([
        'messageId' => 'msg-1',
        'conversationId' => 'conv-1',
    ]);

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://teams-sdk-rest.test/v1/messages'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['userAadId'] === 'azure-user-1'
            && $request['text'] === 'Hallo';
    });
});

it('returns null when conversation is not found', function (): void {
    Http::fake([
        'http://teams-sdk-rest.test/v1/conversations/users/azure-missing' => Http::response([
            'success' => false,
            'error' => 'Conversation not found',
        ], 404),
    ]);

    expect(app(TeamsSdkRestClient::class)->getConversationForUser('azure-missing'))->toBeNull();
});

it('checks teams-sdk-rest health endpoint', function (): void {
    Http::fake([
        'http://teams-sdk-rest.test/health' => Http::response([
            'status' => 'ok',
            'service' => 'teams-sdk-rest',
        ]),
    ]);

    expect(app(TeamsSdkRestClient::class)->isHealthy())->toBeTrue();

    $status = app(TeamsSdkRestClient::class)->getHealthStatus();

    expect($status)->toBe([
        'healthy' => true,
        'service' => 'teams-sdk-rest',
        'base_url' => 'http://teams-sdk-rest.test',
    ]);
});

it('reports unhealthy teams-sdk-rest status on connection failure', function (): void {
    Http::fake([
        'http://teams-sdk-rest.test/health' => Http::response([], 503),
    ]);

    $status = app(TeamsSdkRestClient::class)->getHealthStatus();

    expect($status['healthy'])->toBeFalse()
        ->and($status['base_url'])->toBe('http://teams-sdk-rest.test');
});

it('delegates sdk rest health status to teams bot service', function (): void {
    Http::fake([
        'http://teams-sdk-rest.test/health' => Http::response([
            'status' => 'ok',
            'service' => 'teams-sdk-rest',
        ]),
    ]);

    $status = app(\Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface::class)
        ->getSdkRestHealthStatus();

    expect($status['healthy'])->toBeTrue()
        ->and($status['service'])->toBe('teams-sdk-rest');
});
