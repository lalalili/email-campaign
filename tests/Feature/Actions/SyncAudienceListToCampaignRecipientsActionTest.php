<?php

use Illuminate\Support\Facades\DB;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\AudienceCore\Models\AudienceListRow;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;

function makeSyncList(array $rows): AudienceList
{
    $list = AudienceList::create([
        'name' => '同步測試名單',
        'columns_json' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
        ],
    ]);

    foreach ($rows as $data) {
        AudienceListRow::create([
            'audience_list_id' => $list->id,
            'status' => $data['status'] ?? 'active',
            'data_json' => ['email' => $data['email']],
        ]);
    }

    return $list;
}

function makeSyncCampaign(AudienceList $list): EmailCampaign
{
    return EmailCampaign::factory()->create([
        'audience_list_id' => $list->id,
        'audience_email_column' => 'email',
    ]);
}

it('syncs active rows, dedupes emails and skips invalid addresses', function (): void {
    $list = makeSyncList([
        ['email' => 'a@example.com'],
        ['email' => 'A@Example.com'],
        ['email' => 'not-an-email'],
        ['email' => 'b@example.com'],
        ['email' => 'c@example.com', 'status' => 'inactive'],
    ]);
    $campaign = makeSyncCampaign($list);

    $synced = app(SyncAudienceListToCampaignRecipientsAction::class)->execute($campaign);

    expect($synced)->toBe(2)
        ->and($campaign->recipients()->pluck('email')->all())->toBe(['a@example.com', 'b@example.com'])
        ->and($campaign->fresh()->audience_skipped_count)->toBe(2);
});

it('inserts recipients in batches instead of row by row', function (): void {
    $list = makeSyncList(array_map(
        fn (int $i): array => ['email' => "user{$i}@example.com"],
        range(1, 30),
    ));
    $campaign = makeSyncCampaign($list);

    DB::enableQueryLog();
    $synced = app(SyncAudienceListToCampaignRecipientsAction::class)->execute($campaign);
    $inserts = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'insert into "email_campaign_recipients"'));
    DB::disableQueryLog();

    expect($synced)->toBe(30)
        ->and($inserts)->toHaveCount(1);
});

it('refuses to resync once the campaign has delivery history', function (): void {
    $list = makeSyncList([['email' => 'a@example.com']]);
    $campaign = makeSyncCampaign($list);

    app(SyncAudienceListToCampaignRecipientsAction::class)->execute($campaign);

    $recipient = EmailCampaignRecipient::firstOrFail();
    EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'status' => EmailDeliveryStatus::Sent,
        'tracking_token' => EmailDelivery::generateTrackingToken(),
        'to_email' => $recipient->email,
        'sent_at' => now(),
    ]);

    $synced = app(SyncAudienceListToCampaignRecipientsAction::class)->execute($campaign->fresh());

    expect($synced)->toBe(0)
        ->and(EmailCampaignRecipient::count())->toBe(1)
        ->and(EmailDelivery::count())->toBe(1);
});
