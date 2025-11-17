<?php

namespace Hwkdo\MsGraphLaravel\Commands;

use Illuminate\Console\Command;
use Hwkdo\MsGraphLaravel\Jobs\checkSubscription;

class checkSubscriptions extends Command
{
    public $signature = 'ms-graph-laravel:check-subscriptions';

    public $description = 'Check Subscriptions';

    public function handle(): int
    {
        $this->comment('Checking Subscriptions');
        checkSubscription::dispatch();
        $this->comment('Subscriptions checked');
        return self::SUCCESS;
    }
}
