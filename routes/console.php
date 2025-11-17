<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('ms-graph-laravel:check-subscriptions')->everyFourHours();