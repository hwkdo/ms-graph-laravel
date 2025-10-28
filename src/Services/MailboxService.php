<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface;
use Hwkdo\MsGraphLaravel\Models\Token;
use Illuminate\Support\Carbon;
use Microsoft\Graph\Generated\Models\AutomaticRepliesSetting;
use Microsoft\Graph\Generated\Models\AutomaticRepliesStatus;
use Microsoft\Graph\Generated\Models\MailboxSettings;
use Microsoft\Graph\GraphServiceClient;

class MailboxService implements MsGraphMailboxServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/user-get-mailboxsettings?view=graph-rest-1.0&tabs=http
    */
    private function getAccessToken(): string
    {
        // Get token from the existing token service
        $token = Token::getToken('default');

        return $token;
    }

    public function getSettings($upn)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->mailboxSettings()
            ->get()
            ->wait();
    }

    public function getAutoReplySettings($upn)
    {
        try {
            $mailboxSettings = self::$graph->users()
                ->byUserId($upn)
                ->mailboxSettings()
                ->get()
                ->wait();

            return $mailboxSettings->getAutomaticRepliesSetting();
        } catch (\Exception $e) {
            // Fallback: Create a default disabled setting if there's an enum error
            $automaticRepliesSetting = new AutomaticRepliesSetting;
            $automaticRepliesSetting->setStatus(new AutomaticRepliesStatus(AutomaticRepliesStatus::DISABLED));
            $automaticRepliesSetting->setInternalReplyMessage('');
            $automaticRepliesSetting->setExternalReplyMessage('');

            return $automaticRepliesSetting;
        }
    }

    public function setOutOfOffice($upn, $message, ?Carbon $von = null, ?Carbon $bis = null)
    {
        // Use direct HTTP request workaround to avoid enum issues
        $mailboxSettingsData = [
            'automaticRepliesSetting' => [
                'internalReplyMessage' => $message,
                'externalReplyMessage' => $message,
            ],
        ];

        if (! $von && ! $bis) {
            $mailboxSettingsData['automaticRepliesSetting']['status'] = 'alwaysEnabled';
        } elseif ($von && $bis) {
            $mailboxSettingsData['automaticRepliesSetting']['status'] = 'scheduled';
            $mailboxSettingsData['automaticRepliesSetting']['scheduledStartDateTime'] = [
                'dateTime' => $von->toIso8601String(),
                'timeZone' => 'Europe/Berlin',
            ];
            $mailboxSettingsData['automaticRepliesSetting']['scheduledEndDateTime'] = [
                'dateTime' => $bis->toIso8601String(),
                'timeZone' => 'Europe/Berlin',
            ];
        } elseif (! $von && $bis) {
            $mailboxSettingsData['automaticRepliesSetting']['status'] = 'scheduled';
            $mailboxSettingsData['automaticRepliesSetting']['scheduledStartDateTime'] = [
                'dateTime' => today()->toIso8601String(),
                'timeZone' => 'Europe/Berlin',
            ];
            $mailboxSettingsData['automaticRepliesSetting']['scheduledEndDateTime'] = [
                'dateTime' => $bis->toIso8601String(),
                'timeZone' => 'Europe/Berlin',
            ];
        }

        // Use direct HTTP request workaround to avoid enum issues
        $url = "https://graph.microsoft.com/v1.0/users/{$upn}/mailboxSettings";

        // Create a simple HTTP request using Guzzle
        $httpClient = new \GuzzleHttp\Client;

        $response = $httpClient->patch($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $mailboxSettingsData,
        ]);

        return $response;
    }

    public function removeOutOfOffice($upn)
    {
        // Use direct HTTP request workaround to avoid enum issues
        $mailboxSettingsData = [
            'automaticRepliesSetting' => [
                'status' => 'disabled',
                'internalReplyMessage' => null,
                'externalReplyMessage' => null,
            ],
        ];

        // Use direct HTTP request workaround to avoid enum issues
        $url = "https://graph.microsoft.com/v1.0/users/{$upn}/mailboxSettings";

        // Create a simple HTTP request using Guzzle
        $httpClient = new \GuzzleHttp\Client;

        $response = $httpClient->patch($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $mailboxSettingsData,
        ]);

        return $response;
    }
}
