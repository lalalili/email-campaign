<?php

use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailEvent;
use Lalalili\EmailCampaign\Support\TrackingUrlSigner;

it('wraps links with a signed click tracking url', function (): void {
    config(['email-campaign.tracking.signing_key' => 'tracking-secret']);

    $html = app(InjectEmailTrackingAction::class)->execute(
        '<html><body><a href="https://example.com/page">Read</a></body></html>',
        'token-1',
        'user@example.com',
    );

    $signature = app(TrackingUrlSigner::class)->sign('token-1', 'https://example.com/page');

    expect($html)->toContain('email/track/click/token-1')
        ->and($html)->toContain('u=https%3A%2F%2Fexample.com%2Fpage')
        ->and($html)->toContain('s='.$signature);
});

it('rejects unsigned or unsafe click tracking destinations', function (): void {
    config(['email-campaign.tracking.signing_key' => 'tracking-secret']);

    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);
    $delivery = EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'tracking_token' => 'click-token',
        'to_email' => 'user@example.com',
    ]);

    $this->get(route('email-campaign.track.click', ['token' => $delivery->tracking_token, 'u' => 'https://example.com']))
        ->assertRedirect('/');

    $signature = app(TrackingUrlSigner::class)->sign($delivery->tracking_token, 'javascript:alert(1)');

    $this->get(route('email-campaign.track.click', [
        'token' => $delivery->tracking_token,
        'u' => 'javascript:alert(1)',
        's' => $signature,
    ]))->assertRedirect('/');

    expect(EmailEvent::count())->toBe(0);
});

it('logs and redirects signed click tracking destinations', function (): void {
    config(['email-campaign.tracking.signing_key' => 'tracking-secret']);

    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);
    $delivery = EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'tracking_token' => 'signed-token',
        'to_email' => 'user@example.com',
    ]);
    $destination = 'https://example.com/offers';
    $signature = app(TrackingUrlSigner::class)->sign($delivery->tracking_token, $destination);

    $this->get(route('email-campaign.track.click', [
        'token' => $delivery->tracking_token,
        'u' => $destination,
        's' => $signature,
    ]))->assertRedirect($destination);

    $event = EmailEvent::first();

    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(EmailEventType::Click)
        ->and($event->url)->toBe($destination);
});
