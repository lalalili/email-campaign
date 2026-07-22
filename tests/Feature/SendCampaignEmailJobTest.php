<?php

use Carbon\CarbonImmutable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
use Lalalili\EmailCampaign\Contracts\EmailDeliveryWindow;
use Lalalili\EmailCampaign\Data\RenderedEmail;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Mail\CampaignMail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Support\AllowAllEmailDeliveryWindow;
use Lalalili\EmailCampaign\Support\MailerFactory;

it('dispatches mail and records sent delivery', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create([
        'subject_template' => 'Hello {{ user_name }}',
        'html_template' => '<p>Hi {{ user_name }}</p>',
        'text_template' => null,
    ]);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => 'test@example.com',
        'user_name' => 'Alice',
    ]);

    (new SendCampaignEmailJob($campaign, $recipient))->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    );

    Mail::assertSent(CampaignMail::class, fn ($mail) => $mail->hasTo('test@example.com'));

    $delivery = EmailDelivery::where('email_campaign_id', $campaign->id)
        ->where('email_campaign_recipient_id', $recipient->id)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Sent)
        ->and($delivery->rendered_subject)->toBe('Hello Alice');
});

it('records failed delivery when rendering throws', function () {
    $campaign = EmailCampaign::factory()->create(['subject_template' => 'Hi']);
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    $badRender = new class extends RenderCampaignEmailAction
    {
        public function __construct() {}

        public function execute(
            EmailCampaign $campaign,
            EmailCampaignRecipient $recipient,
        ): RenderedEmail {
            throw new RuntimeException('render error');
        }
    };

    expect(fn () => (new SendCampaignEmailJob($campaign, $recipient))->handle(
        $badRender,
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    ))->toThrow(RuntimeException::class);

    $delivery = EmailDelivery::first();
    expect($delivery->status)->toBe(EmailDeliveryStatus::Failed)
        ->and($delivery->error_message)->toContain('render error');
});

it('skips recipients with missing email without sending mail', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create(['subject_template' => 'Hi']);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => '',
    ]);

    (new SendCampaignEmailJob($campaign, $recipient))->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    );

    Mail::assertNothingSent();

    $delivery = EmailDelivery::where('email_campaign_id', $campaign->id)
        ->where('email_campaign_recipient_id', $recipient->id)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Skipped)
        ->and($delivery->to_email)->toBeNull()
        ->and($delivery->error_message)->toBe('Recipient email is missing or invalid.');
});

it('skips delivery without sending mail when demo safe mode is enabled', function () {
    Mail::fake();
    config(['email-campaign.demo_safe_mode' => true]);

    $campaign = EmailCampaign::factory()->create(['subject_template' => 'Hi']);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => 'test@example.com',
    ]);

    (new SendCampaignEmailJob($campaign, $recipient))->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    );

    Mail::assertNothingSent();

    $delivery = EmailDelivery::where('email_campaign_id', $campaign->id)
        ->where('email_campaign_recipient_id', $recipient->id)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Skipped)
        ->and($delivery->to_email)->toBe('test@example.com')
        ->and($delivery->error_message)->toBe('Email delivery disabled by demo safe mode.');
});

it('skips delivery without sending mail when external communications are disabled', function () {
    Mail::fake();
    config([
        'email-campaign.demo_safe_mode' => false,
        'external-communications.enabled' => false,
    ]);

    $campaign = EmailCampaign::factory()->create(['subject_template' => 'Hi']);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => 'test@example.com',
    ]);

    (new SendCampaignEmailJob($campaign, $recipient))->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    );

    Mail::assertNothingSent();

    $delivery = EmailDelivery::where('email_campaign_id', $campaign->id)
        ->where('email_campaign_recipient_id', $recipient->id)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Skipped)
        ->and($delivery->to_email)->toBe('test@example.com')
        ->and($delivery->error_message)->toBe('Email delivery disabled by external communications setting.');
});

it('has no rate limit middleware when unconfigured', function () {
    config(['email-campaign.rate_limit.max_per_minute' => null]);

    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    expect((new SendCampaignEmailJob($campaign, $recipient))->middleware())->toBe([]);
});

it('applies rate limit middleware when configured', function () {
    config(['email-campaign.rate_limit.max_per_minute' => 30]);

    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    $middleware = (new SendCampaignEmailJob($campaign, $recipient))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class);
});

it('binds an allow-all delivery window by default', function () {
    expect(app(EmailDeliveryWindow::class))->toBeInstanceOf(AllowAllEmailDeliveryWindow::class);
});

it('releases blocked delivery without sending or settling it', function () {
    Mail::fake();
    Queue::fake();

    $campaign = EmailCampaign::factory()->create(['subject_template' => 'Hi']);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => 'blocked@example.com',
    ]);
    $nextAllowedAt = CarbonImmutable::now()->addHours(2);
    $deliveryWindow = new class($nextAllowedAt) implements EmailDeliveryWindow
    {
        public function __construct(private CarbonImmutable $nextAllowedAt) {}

        public function nextAllowedAt(EmailCampaign $campaign): ?CarbonImmutable
        {
            return $this->nextAllowedAt;
        }
    };
    Redis::set(SendCampaignEmailJob::remainingKey($campaign->id), 1);

    $job = new SendCampaignEmailJob($campaign, $recipient);
    $job->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        $deliveryWindow,
    );

    Queue::assertPushed(
        SendCampaignEmailJob::class,
        fn (SendCampaignEmailJob $queuedJob): bool => $queuedJob->delay === $nextAllowedAt
            && $queuedJob->tries === 3,
    );
    Mail::assertNothingSent();

    $delivery = EmailDelivery::where('email_campaign_id', $campaign->id)
        ->where('email_campaign_recipient_id', $recipient->id)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Pending)
        ->and($campaign->fresh()->status)->not->toBe(EmailCampaignStatus::Sent)
        ->and((int) Redis::get(SendCampaignEmailJob::remainingKey($campaign->id)))->toBe(1);
});
