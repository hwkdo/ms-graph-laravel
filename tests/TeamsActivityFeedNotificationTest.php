<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Jobs\SendTeamsActivityFeedNotificationJob;
use Hwkdo\MsGraphLaravel\Services\TeamsActivityFeedNotificationBuilder;
use Hwkdo\MsGraphLaravel\Services\TeamsActivityFeedNotificationService;
use Hwkdo\MsGraphLaravel\Services\TeamsActivityFeedService;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

it('builds a systemDefault activity feed notification body', function (): void {
    $body = app(TeamsActivityFeedNotificationBuilder::class)->build(
        previewText: 'Neue Benachrichtigung',
        actorText: 'Intranet hat dich informiert',
        topicTitle: 'HWKDO Test',
        webUrl: 'https://teams.microsoft.com/l/entity/123',
        teamsAppId: 'catalog-app-id',
    );

    expect($body->getActivityType())->toBe('systemDefault')
        ->and($body->getTeamsAppId())->toBe('catalog-app-id')
        ->and($body->getPreviewText()?->getContent())->toBe('Neue Benachrichtigung')
        ->and($body->getTopic()?->getSource()?->value())->toBe('text')
        ->and($body->getTopic()?->getValue())->toBe('HWKDO Test')
        ->and($body->getTopic()?->getWebUrl())->toBe('https://teams.microsoft.com/l/entity/123')
        ->and($body->getTemplateParameters())->toHaveCount(1)
        ->and($body->getTemplateParameters()[0]->getName())->toBe('systemDefaultText')
        ->and($body->getTemplateParameters()[0]->getValue())->toBe('Intranet hat dich informiert');
});

it('uses entityUrl topic with teams app catalog when no teams deep link is provided', function (): void {
    $body = app(TeamsActivityFeedNotificationBuilder::class)->build(
        previewText: 'Test ohne Deep Link',
        teamsAppId: 'catalog-app-id',
    );

    expect($body->getTopic()?->getSource()?->value())->toBe('entityUrl')
        ->and($body->getTopic()?->getValue())->toBe('https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/catalog-app-id')
        ->and($body->getTopic()?->getWebUrl())->toBeNull();
});

it('uses text topic when a teams deep link is provided', function (): void {
    $body = app(TeamsActivityFeedNotificationBuilder::class)->build(
        previewText: 'Test mit Deep Link',
        topicTitle: 'HWKDO Test',
        webUrl: 'https://teams.microsoft.com/l/entity/123',
        teamsAppId: 'catalog-app-id',
    );

    expect($body->getTopic()?->getSource()?->value())->toBe('text')
        ->and($body->getTopic()?->getValue())->toBe('HWKDO Test')
        ->and($body->getTopic()?->getWebUrl())->toBe('https://teams.microsoft.com/l/entity/123');
});

it('rejects empty preview text when building activity feed notification', function (): void {
    expect(fn () => app(TeamsActivityFeedNotificationBuilder::class)->build('   '))
        ->toThrow(RuntimeException::class, 'Vorschautext');
});

it('dispatches send teams activity feed notification job', function (): void {
    Queue::fake();

    app(TeamsActivityFeedNotificationService::class)->queueNotification(
        'azure-user-1',
        'Vorschau',
        'Actor Text',
        'Thema',
        'https://example.test',
    );

    Queue::assertPushed(SendTeamsActivityFeedNotificationJob::class, function (SendTeamsActivityFeedNotificationJob $job): bool {
        return $job->azureUserId === 'azure-user-1'
            && $job->previewText === 'Vorschau'
            && $job->actorText === 'Actor Text'
            && $job->topicTitle === 'Thema'
            && $job->webUrl === 'https://example.test';
    });
});

it('reports activity feed as enabled when configured', function (): void {
    expect(app(TeamsActivityFeedService::class)->isEnabled())->toBeTrue();
});

it('reports activity feed as disabled when feature flag is off', function (): void {
    config()->set('ms-graph-laravel.teams_activity_feed.enabled', false);

    expect(app(TeamsActivityFeedService::class)->isEnabled())->toBeFalse();
});

it('throws when activity feed is disabled on sync send', function (): void {
    config()->set('ms-graph-laravel.teams_activity_feed.enabled', false);

    expect(fn () => app(TeamsActivityFeedNotificationService::class)->sendNotificationSync(
        'azure-user-1',
        'Test',
    ))->toThrow(RuntimeException::class, 'deaktiviert');
});
