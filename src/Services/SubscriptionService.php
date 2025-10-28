<?php

namespace Hwkdo\MsGraphLaravel\Services;

use GuzzleHttp\Exception\ClientException;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Models\Subscription as GraphSubscription;
use Microsoft\Graph\GraphServiceClient;

class SubscriptionService
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g('subscription');
    }

    /*
    ** https://learn.microsoft.com/en-us/graph/api/subscription-post-subscriptions?view=graph-rest-1.0&tabs=http#request
    */
    public static function subscribe($resource, $notificationUrl, $changeType): bool
    {
        // Create Subscription object for v2
        $subscription = new GraphSubscription;
        $subscription->setChangeType($changeType);
        $subscription->setNotificationUrl($notificationUrl);
        $subscription->setResource($resource);
        // maximale expoiration time fuer mails ist 4230 minuten
        // https://learn.microsoft.com/en-us/graph/api/resources/subscription?view=graph-rest-1.0#maximum-length-of-subscription-per-resource-type
        $expirationDate = \Carbon\Carbon::now()->addMinutes(4320);
        $subscription->setExpirationDateTime(new \DateTime($expirationDate->toIso8601String()));
        $subscription->setClientState(config('ms-graph-laravel.subscription_secret'));
        $subscription->setLatestSupportedTlsVersion('v1_2');

        $response = self::$graph->subscriptions()
            ->post($subscription)
            ->wait();

        if ($response->getId()) {
            $sub = Subscription::create([
                'graph_id' => $response->getId(),
                'resource' => $response->getResource(),
                'notificationUrl' => $response->getNotificationUrl(),
                'expiration' => \Carbon\Carbon::parse($response->getExpirationDateTime()),
            ]);

            return true;
        }

        return false;
    }

    /*
    ** https://learn.microsoft.com/en-us/graph/api/subscription-delete?view=graph-rest-1.0&tabs=http#request
    */
    public static function unsubscribe($id): bool
    {
        $sub = Subscription::where('graph_id', $id)->first();
        try {
            $result = self::$graph->subscriptions()
                ->bySubscriptionId($id)
                ->delete()
                ->wait();

            if ($sub) {
                $sub->delete();
            }

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                Log::info('Subscription bereits entfernt');
                if ($sub) {
                    $sub->delete();
                }

                return true;
            }
        }

        return false;
    }

    /*
    ** https://learn.microsoft.com/en-us/graph/api/subscription-list?view=graph-rest-1.0&tabs=http#example
    */
    public static function list(): array
    {
        $response = self::$graph->subscriptions()
            ->get()
            ->wait();

        $output = [];
        foreach ($response->getValue() as $row) {
            array_push($output, $row->jsonSerialize());
        }

        return $output;
    }
}
