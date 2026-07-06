<?php

use Hwkdo\MsGraphLaravel\Http\Controllers\OutOfOfficeStatusController;
use Hwkdo\MsGraphLaravel\Http\Controllers\SubscriptionController;
use Hwkdo\MsGraphLaravel\Http\Controllers\TeamsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('api/kunden/ms-graph-laravel/teams-webhook', TeamsWebhookController::class)->name('ms-graph-laravel.teams-webhook');

Route::post('api/kunden/ms-graph-subscription/{typ}', SubscriptionController::class)->name('ms-graph-laravel.subscription');

Route::middleware(['auth:api'])
    ->prefix('api')
    ->group(function () {
        Route::get('apps/ms-graph-laravel/out-of-office', [OutOfOfficeStatusController::class, 'index'])
            ->name('ms-graph-laravel.out-of-office.index');
        
        Route::get('apps/ms-graph-laravel/out-of-office/{username}', [OutOfOfficeStatusController::class, 'show'])
            ->name('ms-graph-laravel.out-of-office.show');
    });