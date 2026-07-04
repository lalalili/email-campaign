<?php

use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailEvent;
use Lalalili\EmailCampaign\Models\EmailSuppression;
use Lalalili\EmailCampaign\Support\TrackingUrlSigner;

function makeTrackedDelivery(string $token, string $email = 'user@example.com'): EmailDelivery
{
    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $campaign->id,
        'email' => $email,
    ]);

    return EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'tracking_token' => $token,
        'to_email' => $email,
    ]);
}

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

it('rejects unsigned clicks in production even when the config allows them', function (): void {
    config([
        'email-campaign.tracking.signing_key' => 'tracking-secret',
        'email-campaign.tracking.allow_unsigned_clicks' => true,
    ]);
    $this->app['env'] = 'production';

    $delivery = makeTrackedDelivery('prod-token');

    $this->get(route('email-campaign.track.click', [
        'token' => $delivery->tracking_token,
        'u' => 'https://evil.example.com',
    ]))->assertRedirect('/');

    expect(EmailEvent::count())->toBe(0);
});

it('shows a confirmation page on GET unsubscribe without suppressing the address', function (): void {
    $delivery = makeTrackedDelivery('unsub-token', 'prefetch@example.com');

    $this->get(route('email-campaign.track.unsubscribe', $delivery->tracking_token))
        ->assertOk()
        ->assertSee('確認取消訂閱');

    expect(EmailEvent::count())->toBe(0)
        ->and(EmailSuppression::count())->toBe(0);
});

it('suppresses the address only after the unsubscribe is confirmed via POST', function (): void {
    $delivery = makeTrackedDelivery('unsub-post-token', 'leaving@example.com');

    $this->post(route('email-campaign.track.unsubscribe.confirm', $delivery->tracking_token))
        ->assertOk()
        ->assertSee('已成功取消訂閱');

    expect(EmailEvent::where('type', EmailEventType::Unsubscribe)->count())->toBe(1)
        ->and(EmailSuppression::isSuppressed('leaving@example.com'))->toBeTrue();
});

it('rate limits the public tracking endpoints', function (): void {
    $delivery = makeTrackedDelivery('throttle-token');
    $url = route('email-campaign.track.open', $delivery->tracking_token);

    foreach (range(1, 60) as $i) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertStatus(429);
});
