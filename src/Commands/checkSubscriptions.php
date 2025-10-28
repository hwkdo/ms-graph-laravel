<?php

namespace Hwkdo\MsGraphLaravel\Commands;

use Illuminate\Console\Command;

class checkSubscriptions extends Command
{
    public $signature = 'msgraph:checkSubscriptions';

    public $description = 'Check Subscriptions';

    public function handle(): int
    {
        $this->comment('Checking Subscriptions');

        return self::SUCCESS;
    }
}
