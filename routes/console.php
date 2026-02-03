<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ms-graph-laravel:check-subscriptions')->everyFourHours();

Schedule::job(\Hwkdo\MsGraphLaravel\Jobs\SyncOutOfOfficeStatusJob::class)->everyFiveMinutes();