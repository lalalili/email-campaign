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

    /*
    |--------------------------------------------------------------------------
    | SMTP Send Rate Limit
    |--------------------------------------------------------------------------
    | 每分鐘最多寄出的封數上限，避免超過 SMTP 供應商的速率而被暫時封鎖。
    | null＝不限制。超過上限的 SendCampaignEmailJob 會被 release 回佇列稍後重試，
    | 由 RateLimited job middleware（具名 limiter「email-campaign-send」）處理。
    */
    'rate_limit' => [
        'max_per_minute' => env('EMAIL_CAMPAIGN_RATE_LIMIT_PER_MINUTE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Routes Middleware
    |--------------------------------------------------------------------------
    | Applied to the public open/click/unsubscribe endpoints. Keep a throttle
    | entry here: these routes are unauthenticated and abusable without it.
    */
    'route_middleware' => ['throttle:60,1'],

    'tracking' => [
        'signing_key' => env('EMAIL_CAMPAIGN_TRACKING_SIGNING_KEY'),
        // 生產環境強制簽章驗證（TrackingUrlSigner 會忽略此開關），避免 click 端點淪為 open redirect。
        'allow_unsigned_clicks' => env('EMAIL_CAMPAIGN_ALLOW_UNSIGNED_CLICKS', false),
    ],
];
