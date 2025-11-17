<?php

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\MsGraphLaravel\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    public function handle(SubscriptionService $subscriptionService): bool
    {
        return $subscriptionService->checkAndSyncSubscriptions();
    }
}
