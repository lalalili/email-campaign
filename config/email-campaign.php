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
    'scheduler_enabled' => true,

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
