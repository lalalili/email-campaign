<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Contracts\EmailDeliveryWindow;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Support\MailerFactory;

it('dispatches jobs and marks the campaign as sending', function () {
    Queue::fake();

    $campaign = EmailCampaign::factory()->create(['status' => EmailCampaignStatus::Draft]);
    EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    expect(app(SendCampaignAction::class)->execute($campaign))->toBeTrue();

    Queue::assertPushed(SendCampaignEmailJob::class, 1);
    expect($campaign->fresh()->status)->toBe(EmailCampaignStatus::Sending);
});

it('does not dispatch twice when execute is called again on the same campaign', function () {
    Queue::fake();

    $campaign = EmailCampaign::factory()->create(['status' => EmailCampaignStatus::Scheduled]);
    EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    expect(app(SendCampaignAction::class)->execute($campaign))->toBeTrue()
        ->and(app(SendCampaignAction::class)->execute($campaign->fresh()))->toBeFalse();

    Queue::assertPushed(SendCampaignEmailJob::class, 1);
});

it('refuses to dispatch campaigns that are already sending or sent', function (EmailCampaignStatus $status) {
    Queue::fake();

    $campaign = EmailCampaign::factory()->create(['status' => $status]);
    EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    expect(app(SendCampaignAction::class)->execute($campaign))->toBeFalse();

    Queue::assertNothingPushed();
    expect($campaign->fresh()->status)->toBe($status);
})->with([
    'sending' => EmailCampaignStatus::Sending,
    'sent' => EmailCampaignStatus::Sent,
]);

it('does not resend mail for a delivery that is already sent', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create([
        'subject_template' => 'Hi',
        'html_template' => '<p>Hi</p>',
    ]);
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => 'sent@example.com',
    ]);
    EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'status' => EmailDeliveryStatus::Sent,
        'tracking_token' => EmailDelivery::generateTrackingToken(),
        'to_email' => 'sent@example.com',
        'sent_at' => now(),
    ]);

    (new SendCampaignEmailJob($campaign, $recipient))->handle(
        app(RenderCampaignEmailAction::class),
        app(MailerFactory::class),
        app(InjectEmailTrackingAction::class),
        app(EmailDeliveryWindow::class),
    );

    Mail::assertNothingSent();
    expect(EmailDelivery::count())->toBe(1);
});
