<?php

use Hwkdo\MsGraphLaravel\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('api/kunden/ms-graph-subscription/{typ}', SubscriptionController::class)->name('ms-graph-laravel.subscription');
