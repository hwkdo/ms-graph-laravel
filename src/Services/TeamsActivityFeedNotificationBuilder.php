<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\KeyValuePair;
use Microsoft\Graph\Generated\Models\TeamworkActivityTopic;
use Microsoft\Graph\Generated\Models\TeamworkActivityTopicSource;
use Microsoft\Graph\Generated\Users\Item\Teamwork\SendActivityNotification\SendActivityNotificationPostRequestBody;
use RuntimeException;

class TeamsActivityFeedNotificationBuilder
{
    public function build(
        string $previewText,
        ?string $actorText = null,
        ?string $topicTitle = null,
        ?string $webUrl = null,
        ?string $teamsAppId = null,
    ): SendActivityNotificationPostRequestBody {
        $preview = trim($previewText);

        if ($preview === '') {
            throw new RuntimeException('Vorschautext der Activity-Feed-Benachrichtigung darf nicht leer sein.');
        }

        $activityType = (string) config('ms-graph-laravel.teams_activity_feed.activity_type', 'systemDefault');
        $resolvedTeamsAppId = $teamsAppId ?? config('ms-graph-laravel.teams_bot.teams_app_id');
        $topicTitleValue = filled($topicTitle)
            ? $topicTitle
            : (string) config('ms-graph-laravel.teams_activity_feed.topic_title', 'HWKDO Intranet');
        $configuredWebUrl = filled($webUrl)
            ? $webUrl
            : config('ms-graph-laravel.teams_activity_feed.topic_web_url');

        $topic = $this->buildTopic($topicTitleValue, $configuredWebUrl, $resolvedTeamsAppId);

        $previewBody = new ItemBody;
        $previewBody->setContent(mb_substr($preview, 0, 150));

        $body = new SendActivityNotificationPostRequestBody;
        $body->setActivityType($activityType);
        $body->setTopic($topic);
        $body->setPreviewText($previewBody);

        if (filled($resolvedTeamsAppId)) {
            $body->setTeamsAppId((string) $resolvedTeamsAppId);
        }

        if ($activityType === 'systemDefault') {
            $actorValue = filled($actorText) ? $actorText : $preview;

            $templateParameter = new KeyValuePair;
            $templateParameter->setName('systemDefaultText');
            $templateParameter->setValue(mb_substr($actorValue, 0, 150));

            $body->setTemplateParameters([$templateParameter]);
        }

        return $body;
    }

    private function buildTopic(string $topicTitle, mixed $webUrl, mixed $teamsAppId): TeamworkActivityTopic
    {
        $topic = new TeamworkActivityTopic;

        if (self::isTeamsDeepLink($webUrl)) {
            $topic->setSource(new TeamworkActivityTopicSource(TeamworkActivityTopicSource::TEXT));
            $topic->setValue($topicTitle);
            $topic->setWebUrl((string) $webUrl);

            return $topic;
        }

        if (! filled($teamsAppId)) {
            throw new RuntimeException('MSGRAPH_TEAMS_APP_CATALOG_ID ist für Activity-Feed-Benachrichtigungen erforderlich.');
        }

        $topic->setSource(new TeamworkActivityTopicSource(TeamworkActivityTopicSource::ENTITY_URL));
        $topic->setValue('https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/'.$teamsAppId);

        return $topic;
    }

    public static function isTeamsDeepLink(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        return (bool) preg_match('#^https://teams\.microsoft\.(com|us|gov)(/l/|/dl/)#i', trim($url));
    }
}
