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

    public function getAutoReplySettings($upn, $raw = true)
    {
        try {
            $mailboxSettings = self::$graph->users()
                ->byUserId($upn)
                ->mailboxSettings()
                ->get()
                ->wait();

            $data =  $mailboxSettings->getAutomaticRepliesSetting();
            if ($raw) {
                return $data;
            }
            return [
                'status' => $data->getStatus()->value(),
                'internalReplyMessage' => $data->getInternalReplyMessage(),
                'externalReplyMessage' => $data->getExternalReplyMessage(),
                'scheduledStartDateTime' => ["dateTime" => $data->getScheduledStartDateTime()->getDateTime()],
                'scheduledEndDateTime' => ["dateTime" => $data->getScheduledEndDateTime()->getDateTime()],
            ];
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

    public function setInboxForwarding(string $mailboxUpn, string $forwardToSmtp): void
    {
        $this->clearInboxForwarding($mailboxUpn);

        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailboxUpn).'/mailFolders/inbox/messageRules';
        $httpClient = new \GuzzleHttp\Client;

        $httpClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'displayName' => MsGraphMailboxServiceInterface::INTRANET_AUSTRITT_FORWARD_RULE_NAME,
                'sequence' => 1,
                'isEnabled' => true,
                'conditions' => new \stdClass,
                'actions' => [
                    'forwardTo' => [
                        [
                            'emailAddress' => [
                                'address' => $forwardToSmtp,
                            ],
                        ],
                    ],
                    'stopProcessingRules' => true,
                ],
            ],
        ]);
    }

    public function clearInboxForwarding(string $mailboxUpn): void
    {
        foreach ($this->listInboxRules($mailboxUpn) as $rule) {
            $name = (string) ($rule['displayName'] ?? '');
            $id = (string) ($rule['id'] ?? '');
            if ($id === '' || $name !== MsGraphMailboxServiceInterface::INTRANET_AUSTRITT_FORWARD_RULE_NAME) {
                continue;
            }

            $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailboxUpn).'/mailFolders/inbox/messageRules/'.rawurlencode($id);
            $httpClient = new \GuzzleHttp\Client;
            $httpClient->delete($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getAccessToken(),
                ],
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listInboxRules(string $mailboxUpn): array
    {
        $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($mailboxUpn).'/mailFolders/inbox/messageRules';
        $httpClient = new \GuzzleHttp\Client;

        $response = $httpClient->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Accept' => 'application/json',
            ],
        ]);

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            return [];
        }

        $values = $decoded['value'] ?? [];

        return is_array($values) ? array_values($values) : [];
    }
}
