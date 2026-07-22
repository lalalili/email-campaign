<?php

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;

function createSmtpProfile(): EmailSmtpProfile
{
    return EmailSmtpProfile::create([
        'name' => '主要 SMTP',
        'mailer' => 'smtp',
        'from_address' => 'sender@example.test',
        'from_name' => 'Sender',
    ]);
}

it('soft deletes and restores email campaigns without removing their SMTP relationship', function (): void {
    $profile = createSmtpProfile();
    $campaign = EmailCampaign::factory()->create([
        'smtp_profile_id' => $profile->id,
        'status' => EmailCampaignStatus::Draft,
    ]);

    $campaign->delete();

    expect($campaign->trashed())->toBeTrue()
        ->and(EmailCampaign::withTrashed()->find($campaign->id)?->smtp_profile_id)->toBe($profile->id);

    $campaign->restore();

    expect($campaign->fresh())->not->toBeNull();
});

it('only soft deletes campaigns in safe terminal or draft states', function (EmailCampaignStatus $status, bool $canDelete): void {
    $campaign = EmailCampaign::factory()->create(['status' => $status]);

    if (! $canDelete) {
        expect(fn () => $campaign->delete())->toThrow(DomainException::class);

        return;
    }

    $campaign->delete();

    expect($campaign->trashed())->toBeTrue();
})->with([
    'draft' => [EmailCampaignStatus::Draft, true],
    'sent' => [EmailCampaignStatus::Sent, true],
    'failed' => [EmailCampaignStatus::Failed, true],
    'scheduled' => [EmailCampaignStatus::Scheduled, false],
    'sending' => [EmailCampaignStatus::Sending, false],
]);

it('blocks soft deleting an SMTP profile used by scheduled or sending campaigns', function (EmailCampaignStatus $status): void {
    $profile = createSmtpProfile();
    EmailCampaign::factory()->create([
        'smtp_profile_id' => $profile->id,
        'status' => $status,
    ]);

    expect(fn () => $profile->delete())
        ->toThrow(DomainException::class, '仍有排程中或寄送中的 Email 活動使用此 SMTP 設定檔');
})->with([
    EmailCampaignStatus::Scheduled,
    EmailCampaignStatus::Sending,
]);

it('soft deletes and restores an SMTP profile when no active campaign is in flight', function (): void {
    $profile = createSmtpProfile();
    EmailCampaign::factory()->create([
        'smtp_profile_id' => $profile->id,
        'status' => EmailCampaignStatus::Sent,
    ]);

    $profile->delete();

    expect($profile->trashed())->toBeTrue();

    $profile->restore();

    expect($profile->fresh())->not->toBeNull();
});
