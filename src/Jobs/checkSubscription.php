<?php

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Models\Subscription;
use Hwkdo\MsGraphLaravel\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class checkSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $mustHaveSubscriptions = \Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping::getActiveSubscriptions();

        foreach ($mustHaveSubscriptions as $subscription) {
            $name = $subscription->name;
            $values = $subscription->getSubscriptionData();

            $registeredSubscription = Subscription::where('resource', $values['resource'])
                ->where('notificationUrl', $values['notificationUrl'])
                ->latest()
                ->first();

            if (! $registeredSubscription) {
                // wenn mustHave noch nicht registiert
                Log::info('MsGraph - MustHave Subscription '.$name.' noch nicht registiert');
                $result = $subscriptionService->subscribe($values['resource'], $values['notificationUrl'], $values['changeType']);

                if ($result) {
                    Log::info('MsGraph - MustHave Subscription '.$name.' erfolgreich registiert');
                    // return true;
                } else {
                    Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht registriert werden');
                    // return false;
                }
            } else {
                // wenn mustHave bereits registriert
                Log::info('MsGraph - MustHave Subscription '.$name.' bereits registriert');

                $diffHours = \Carbon\Carbon::now()->diffInHours($registeredSubscription->expiration);
                if ($registeredSubscription->expiration > \Carbon\Carbon::now() && $diffHours > 24) {
                    Log::info('Expiration ist größer JETZT und loaenger als 24 Stunden gueltig.');
                    // return true;
                } else {
                    Log::info('Braucht re-subscribe');
                    try {
                        $subscriptionService->unsubscribe($registeredSubscription->graph_id);
                    } catch (\Exception $e) {
                        Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht unsubscribed werden');
                        // return false;
                    }
                    $result = $subscriptionService->subscribe($values['resource'], $values['notificationUrl'], $values['changeType']);

                    if ($result) {
                        Log::info('MsGraph - MustHave Subscription '.$name.' erfolgreich registiert');
                        $registeredSubscription->delete();
                        // return true;
                    } else {
                        Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht registriert werden');
                        // return false;
                    }
                }

                //                if ($registeredSubscription->expiration > \Carbon\Carbon::now())
                //                {
                //                    Log::info('Expiration ist groesser Jetzt, also in der Zukunft und somit noch gültig');
                //
                //                    //wenn subscription weniger als 24 Stunden gültig
                //                    if($diffHours < 24)
                //                    {
                //                        Log::info('MsGraph - MustHave Subscription '.$name.' noch '.$diffHours.' Stunden gueltig. Versuche re-subscribe');
                //
                //                        $subscriptionService->unsubscribe($registeredSubscription->graph_id);
                //                        $result = $subscriptionService->subscribe($values['resource'],$values['notificationUrl']);
                //
                //                        if ($result) {
                //                            Log::info('MsGraph - MustHave Subscription '.$name.' erfolgreich registiert');
                //                            //return true;
                //                        } else
                //                        {
                //                            Log::error('MsGraph - MustHave Subscription '.$name.' konnte nicht registriert werden');
                //                            //return false;
                //                        }
                //                    }
                //                }
                //                elseif ($registeredSubscription->expiration < \Carbon\Carbon::now())
                //                {
                //                    Log::info('Expiration ist kleiner Jetzt, also in der Vergangenheit und somit nicht mehr gültig');
                //
                //                }
                //                else Log::info('MsGraph - MustHave Subscription '.$name.' noch '.$diffHours.' Stunden gueltig. Keine Aktion notwendig');
                //                //return true;
            }
        }

        return true;
    }
}
