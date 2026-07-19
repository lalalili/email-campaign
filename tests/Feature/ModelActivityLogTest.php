<?php

use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Spatie\Activitylog\Models\Activity;

it('logs created/updated/deleted for EmailCampaign', function (): void {
    Activity::query()->delete();

    $campaign = EmailCampaign::factory()->create();
    expect(Activity::query()->where('event', 'created')->where('subject_type', EmailCampaign::class)->count())->toBe(1);

    $campaign->update(['name' => '改名稱']);
    expect(Activity::query()->where('event', 'updated')->where('subject_type', EmailCampaign::class)->count())->toBe(1);

    $campaign->delete();
    expect(Activity::query()->where('event', 'deleted')->where('subject_type', EmailCampaign::class)->count())->toBe(1);
});

it('only logs updated/deleted for EmailCampaignRecipient, not created', function (): void {
    $campaign = EmailCampaign::factory()->create();
    Activity::query()->delete();

    $recipient = EmailCampaignRecipient::create([
        'email_campaign_id' => $campaign->id,
        'email' => 'r@example.test',
    ]);
    expect(Activity::query()->where('subject_type', EmailCampaignRecipient::class)->count())->toBe(0);

    $recipient->update(['user_name' => '改名稱']);
    expect(Activity::query()->where('event', 'updated')->where('subject_type', EmailCampaignRecipient::class)->count())->toBe(1);

    $recipient->delete();
    expect(Activity::query()->where('event', 'deleted')->where('subject_type', EmailCampaignRecipient::class)->count())->toBe(1);
});

it('logs EmailSmtpProfile changes without ever exposing the password', function (): void {
    Activity::query()->delete();

    $profile = EmailSmtpProfile::create([
        'name' => 'SMTP 設定',
        'mailer' => 'smtp',
        'host' => 'smtp.example.test',
        'port' => 587,
        'username' => 'user',
        'password' => 'super-secret',
        'from_address' => 'noreply@example.test',
        'from_name' => '寄件人',
        'is_default' => false,
    ]);
    expect(Activity::query()->where('event', 'created')->where('subject_type', EmailSmtpProfile::class)->count())->toBe(1);

    $profile->update(['password' => 'another-secret', 'from_name' => '改寄件人']);
    expect(Activity::query()->where('event', 'updated')->where('subject_type', EmailSmtpProfile::class)->count())->toBe(1);

    $allLoggedText = Activity::query()
        ->where('subject_type', EmailSmtpProfile::class)
        ->get()
        ->map(fn (Activity $activity): string => json_encode([
            $activity->attribute_changes,
            $activity->properties,
        ]))
        ->implode(' ');

    expect($allLoggedText)
        ->not->toContain('super-secret')
        ->and($allLoggedText)->not->toContain('another-secret')
        ->and($allLoggedText)->not->toContain('"password"');

    $profile->delete();
    expect(Activity::query()->where('event', 'deleted')->where('subject_type', EmailSmtpProfile::class)->count())->toBe(1);
});
