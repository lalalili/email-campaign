<?php

use Illuminate\Support\Facades\Route;
use Lalalili\EmailCampaign\Http\Controllers\EmailTrackingController;

Route::prefix('email/track')
    ->name('email-campaign.track.')
    ->middleware(config('email-campaign.route_middleware', ['throttle:60,1']))
    ->group(function (): void {
        Route::get('open/{token}', [EmailTrackingController::class, 'open'])->name('open');
        Route::get('click/{token}', [EmailTrackingController::class, 'click'])->name('click');
        Route::get('unsubscribe/{token}', [EmailTrackingController::class, 'unsubscribe'])->name('unsubscribe');
        Route::post('unsubscribe/{token}', [EmailTrackingController::class, 'confirmUnsubscribe'])->name('unsubscribe.confirm');
    });
