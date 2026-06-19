<?php

use Illuminate\Support\Facades\Mail;
use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
use Lalalili\EmailCampaign\Data\RenderedEmail;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Mail\CampaignMail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
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
