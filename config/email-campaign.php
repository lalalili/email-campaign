<?php

use Lalalili\EmailCampaign\Providers\RecipientVariableProvider;
use Lalalili\EmailCampaign\Providers\SystemVariableProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Variable Providers
    |--------------------------------------------------------------------------
    | Providers are resolved in order; later providers overwrite earlier ones.
    | Add your own provider class here or push via VariableProviderRegistry.
    */
    'providers' => [
        SystemVariableProvider::class,
        RecipientVariableProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    | Set to false to disable the automatic ScheduleDueCampaignsAction cron.
    */
    'scheduler_enabled' => env('EMAIL_CAMPAIGN_SCHEDULER_ENABLED', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Demo Safe Mode
    |--------------------------------------------------------------------------
    | This project currently exposes email campaign features from the demo panel.
    | Keep production safe by recording delivery attempts without contacting a
    | real mail transport unless this is explicitly disabled.
    */
    'demo_safe_mode' => env('EMAIL_CAMPAIGN_DEMO_SAFE_MODE', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Queue connection and name for SendCampaignEmailJob.
    */
    'queue' => [
        'connection' => env('EMAIL_CAMPAIGN_QUEUE_CONNECTION'),
        'name' => env('EMAIL_CAMPAIGN_QUEUE', 'default'),
    ],
];
