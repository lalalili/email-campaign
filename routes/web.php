<?php

use Illuminate\Support\Facades\Route;
use Lalalili\EmailCampaign\Http\Controllers\EmailTrackingController;

Route::prefix('email/track')->name('email-campaign.track.')->group(function (): void {
    Route::get('open/{token}', [EmailTrackingController::class, 'open'])->name('open');
    Route::get('click/{token}', [EmailTrackingController::class, 'click'])->name('click');
    Route::get('unsubscribe/{token}', [EmailTrackingController::class, 'unsubscribe'])->name('unsubscribe');
});
