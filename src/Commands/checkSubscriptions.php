<?php

namespace Hwkdo\MsGraphLaravel\Commands;

use Hwkdo\MsGraphLaravel\Services\SubscriptionService;
use Illuminate\Console\Command;

class checkSubscriptions extends Command
{
    public $signature = 'ms-graph-laravel:check-subscriptions';

    public $description = 'Check Subscriptions';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $this->comment('Checking Subscriptions');
        $subscriptionService->checkAndSyncSubscriptions();
        $this->comment('Subscriptions checked');
        return self::SUCCESS;
    }
}
