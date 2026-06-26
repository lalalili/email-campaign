<?php

use Illuminate\Support\Facades\Mail;
use Lalalili\EmailCampaign\Actions\SendTestCampaignEmailAction;
use Lalalili\EmailCampaign\Mail\CampaignMail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

it('sends a test email to the given address and reports missing variables', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create([
        'subject_template' => 'Hi {{ user_name }}',
        'html_template' => '<p>{{ user_name }} — {{ coupon_code_xyz }}</p>',
    ]);
    $recipient = EmailCampaignRecipient::factory()->for($campaign, 'campaign')->create(['user_name' => 'Tester']);

    $rendered = app(SendTestCampaignEmailAction::class)->execute($campaign, 'tester@example.com', $recipient);

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('tester@example.com'));
    expect($rendered->missingVariables)->toContain('coupon_code_xyz');
});

it('does not write any delivery rows for a test send', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create();
    EmailCampaignRecipient::factory()->for($campaign, 'campaign')->create();

    app(SendTestCampaignEmailAction::class)->execute($campaign, 'tester@example.com');

    expect($campaign->deliveries()->count())->toBe(0);
    Mail::assertSentCount(1);
});

it('falls back to an ad-hoc recipient when the campaign has none', function () {
    Mail::fake();

    $campaign = EmailCampaign::factory()->create();

    app(SendTestCampaignEmailAction::class)->execute($campaign, 'solo@example.com');

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('solo@example.com'));
});

it('renders but does not send test email when external communications are disabled', function () {
    Mail::fake();
    config(['external-communications.enabled' => false]);

    $campaign = EmailCampaign::factory()->create([
        'subject_template' => 'Hi {{ user_name }}',
    ]);
    $recipient = EmailCampaignRecipient::factory()->for($campaign, 'campaign')->create(['user_name' => 'Tester']);

    $rendered = app(SendTestCampaignEmailAction::class)->execute($campaign, 'tester@example.com', $recipient);

    expect($rendered->subject)->toBe('Hi Tester');
    Mail::assertNothingSent();
});
