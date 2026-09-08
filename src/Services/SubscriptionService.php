<?php

namespace Hwkdo\MsGraphLaravel\Services;

use GuzzleHttp\Exception\ClientException;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Models\Subscription as GraphSubscription;
use Microsoft\Graph\GraphServiceClient;
use Throwable;

class SubscriptionService
{
    protected GraphServiceClient $graph;

    public function __construct(public Client $client)
    {
        $this->graph = ($this->client)('subscription');
    }

    /*
    ** https://learn.microsoft.com/en-us/graph/api/subscription-post-subscriptions?view=graph-rest-1.0&tabs=http#request
    */
    public function subscribe(string $resource, string $notificationUrl, string $changeType): bool
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

        $response = $this->graph->subscriptions()
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
    public function unsubscribe(string $id): bool
    {
        $subscription = Subscription::where('graph_id', $id)->first();

        if (! $subscription) {
            return false;
        }

        try {
            $this->graph->subscriptions()
                ->bySubscriptionId($id)
                ->delete()
                ->wait();
        } catch (ClientException $exception) {
            $status = $exception->getResponse()?->getStatusCode();

            if (in_array($status, [404, 410], true)) {
                Log::info('SubscriptionService - Graph-Subscription bereits entfernt.', [
                    'graph_id' => $id,
                    'status' => $status,
                ]);
            } else {
                Log::warning('SubscriptionService - Graph-API Fehler beim Entfernen.', [
                    'graph_id' => $id,
                    'status' => $status,
                    'message' => $exception->getMessage(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('SubscriptionService - Unerwarteter Fehler beim Entfernen.', [
                'graph_id' => $id,
                'message' => $exception->getMessage(),
            ]);
        }

        $subscription->delete();

        return true;
    }

    /*
    ** https://learn.microsoft.com/en-us/graph/api/subscription-list?view=graph-rest-1.0&tabs=http#example
    */
    public function list(): array
    {
        $response = $this->graph->subscriptions()
            ->get()
            ->wait();

        $output = [];
        foreach ($response->getValue() as $row) {
            array_push($output, $row);
        }

        return $output;
    }

    /**
     * Check and sync all active subscriptions.
     * Ensures all required subscriptions are registered and re-subscribes if needed.
     */
    public function checkAndSyncSubscriptions(): bool
    {
        $mustHaveSubscriptions = GraphWebhookJobMapping::getActiveSubscriptions();

        foreach ($mustHaveSubscriptions as $mapping) {
            $this->syncMapping($mapping);
        }

        return true;
    }

    /**
     * Registriert bzw. erneuert genau eine Mapping-Subscription bei Microsoft Graph.
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function syncMapping(GraphWebhookJobMapping $mapping, bool $force = false): array
    {
        $name = $mapping->name ?: $mapping->webhook_type;

        if (! $mapping->is_active) {
            return [
                'ok' => false,
                'status' => 'inactive',
                'message' => "Mapping [{$name}] ist inaktiv.",
            ];
        }

        if (! $mapping->hasSubscriptionData()) {
            return [
                'ok' => false,
                'status' => 'incomplete',
                'message' => "Mapping [{$name}] hat unvollständige Subscription-Daten (resource/notification_url/change_type).",
            ];
        }

        $values = $mapping->getSubscriptionData();

        $registeredSubscription = Subscription::query()
            ->where('resource', $values['resource'])
            ->where('notificationUrl', $values['notificationUrl'])
            ->latest()
            ->first();

        if (! $registeredSubscription) {
            Log::info('MsGraph - MustHave Subscription '.$name.' noch nicht registriert');
            $result = $this->subscribe($values['resource'], $values['notificationUrl'], $values['changeType']);

            if ($result) {
                Log::info('MsGraph - MustHave Subscription '.$name.' erfolgreich registriert');

                return [
                    'ok' => true,
                    'status' => 'created',
                    'message' => "Subscription [{$name}] wurde registriert.",
                ];
            }

            Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht registriert werden');

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => "Subscription [{$name}] konnte nicht registriert werden.",
            ];
        }

        Log::info('MsGraph - MustHave Subscription '.$name.' bereits registriert');

        $stillValid = $registeredSubscription->expiration > \Carbon\Carbon::now()
            && \Carbon\Carbon::now()->diffInHours($registeredSubscription->expiration) > 24;

        if ($stillValid && ! $force) {
            Log::info('Expiration ist größer JETZT und loaenger als 24 Stunden gueltig.');

            return [
                'ok' => true,
                'status' => 'still_valid',
                'message' => "Subscription [{$name}] ist bereits registriert und noch länger als 24h gültig.",
            ];
        }

        Log::info('Braucht re-subscribe');

        try {
            $this->unsubscribe($registeredSubscription->graph_id);
        } catch (\Exception $e) {
            Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht unsubscribed werden');
        }

        $result = $this->subscribe($values['resource'], $values['notificationUrl'], $values['changeType']);

        if ($result) {
            Log::info('MsGraph - MustHave Subscription '.$name.' erfolgreich registriert');

            return [
                'ok' => true,
                'status' => 'renewed',
                'message' => "Subscription [{$name}] wurde erneuert.",
            ];
        }

        Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht registriert werden');

        return [
            'ok' => false,
            'status' => 'failed',
            'message' => "Subscription [{$name}] konnte nicht erneuert werden.",
        ];
    }
}
