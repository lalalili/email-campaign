<?php

use Lalalili\EmailCampaign\Actions\LogEmailEventAction;
use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;

function makeCountDelivery(): EmailDelivery
{
    $campaign = EmailCampaign::factory()->create();
    $recipient = EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    return EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'to_email' => 'user@example.com',
    ]);
}

it('increments open_count and sets opened_at on open events', function (): void {
    $delivery = makeCountDelivery();
    $action = app(LogEmailEventAction::class);

    $action->execute($delivery, EmailEventType::Open);
    $action->execute($delivery, EmailEventType::Open);

    $delivery->refresh();

    expect($delivery->open_count)->toBe(2)
        ->and($delivery->click_count)->toBe(0)
        ->and($delivery->opened_at)->not->toBeNull();
});

it('increments click_count on click events without touching open_count', function (): void {
    $delivery = makeCountDelivery();
    $action = app(LogEmailEventAction::class);

    $action->execute($delivery, EmailEventType::Click);
    $action->execute($delivery, EmailEventType::Click);
    $action->execute($delivery, EmailEventType::Click);

    $delivery->refresh();

    expect($delivery->click_count)->toBe(3)
        ->and($delivery->open_count)->toBe(0)
        ->and($delivery->opened_at)->toBeNull();
});

it('defaults both counters to zero for a persisted new delivery', function (): void {
    $delivery = makeCountDelivery()->fresh();

    expect($delivery->open_count)->toBe(0)
        ->and($delivery->click_count)->toBe(0);
});
