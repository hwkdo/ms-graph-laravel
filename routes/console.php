<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ms-graph-laravel:check-subscriptions')->dailyAt('01:30');