<?php

namespace Lalalili\EmailCampaign;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Lalalili\EmailCampaign\Actions\ScheduleDueCampaignsAction;
use Lalalili\EmailCampaign\Listeners\HandleSurveyInvitationDispatched;
use Lalalili\EmailCampaign\Support\MailerFactory;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EmailCampaignServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('email-campaign')
            ->hasConfigFile('email-campaign')
            ->hasViews('email-campaign')
            ->hasMigrations([
                '2026_04_24_000001_create_email_smtp_profiles_table',
                '2026_04_24_000002_create_email_campaigns_table',
                '2026_04_24_000003_create_email_campaign_recipients_table',
                '2026_04_24_000004_create_email_deliveries_table',
                '2026_04_29_000001_add_audience_list_to_email_campaigns_table',
                '2026_04_29_000002_add_audience_list_row_to_email_campaign_recipients_table',
                '2026_05_06_223506_add_survey_to_email_campaigns_table',
                '2026_05_20_000001_add_tracking_token_to_email_deliveries_table',
                '2026_05_20_000002_create_email_events_table',
                '2026_05_20_000003_create_email_suppressions_table',
                '2026_05_20_000004_make_email_campaign_id_nullable_on_email_deliveries',
                '2026_05_23_000001_add_marketing_activity_id_to_email_campaigns_table',
                '2026_07_02_000001_add_campaign_status_index_to_email_deliveries_table',
                '2026_07_18_000001_add_event_counts_to_email_deliveries_table',
                '2026_07_23_003948_add_deleted_at_to_email_campaign_tables',
            ])
            ->runsMigrations()
            ->hasRoutes(['web']);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(VariableProviderRegistry::class, function ($app) {
            return new VariableProviderRegistry($app);
        });

        $this->app->singleton(MailerFactory::class);
    }

    public function bootingPackage(): void
    {
        if (class_exists($surveyInvitationDispatched = 'Lalalili\\SurveyCore\\Events\\SurveyInvitationDispatched')) {
            Event::listen($surveyInvitationDispatched, HandleSurveyInvitationDispatched::class);
        }

        $registry = $this->app->make(VariableProviderRegistry::class);

        foreach (config('email-campaign.providers', []) as $provider) {
            $registry->register($provider);
        }

        RateLimiter::for('email-campaign-send', function () {
            $maxPerMinute = config('email-campaign.rate_limit.max_per_minute');

            return $maxPerMinute
                ? Limit::perMinute((int) $maxPerMinute)
                : Limit::none();
        });

        if (config('email-campaign.scheduler_enabled', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule
                    ->call(ScheduleDueCampaignsAction::class)
                    ->name('email-campaign:schedule-due-campaigns')
                    ->everyMinute()
                    ->withoutOverlapping(10);
            });
        }
    }
}
