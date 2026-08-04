<?php

use Lalalili\EmailCampaign\Actions\ImportCampaignRecipientsFromRowsAction;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

beforeEach(function () {
    $this->action = new ImportCampaignRecipientsFromRowsAction();
    $this->campaign = EmailCampaign::factory()->create();
});

it('creates recipients and folds extra columns into payload_json with original headers', function () {
    $result = $this->action->execute($this->campaign, [
        ['email' => 'alice@example.com', 'user_name' => '王小明', 'external_id' => 'A-1', '車牌號碼' => 'ABC-1234'],
        ['email' => 'bob@example.com', 'user_name' => 'Bob', 'external_id' => 'B-2', '服務據點' => '台北服務中心'],
    ]);

    expect($result['imported'])->toBe(2)
        ->and($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(0)
        ->and($result['errors'])->toBe([]);

    $alice = EmailCampaignRecipient::where('email', 'alice@example.com')->first();
    expect($alice->user_name)->toBe('王小明')
        ->and($alice->external_id)->toBe('A-1')
        ->and($alice->payload_json)->toBe(['車牌號碼' => 'ABC-1234']);
});

it('skips blank emails and records an error for invalid emails', function () {
    $result = $this->action->execute($this->campaign, [
        ['email' => '', 'user_name' => 'Blank'],
        ['email' => 'not-an-email', 'user_name' => 'Invalid'],
        ['email' => 'valid@example.com', 'user_name' => 'Valid'],
    ]);

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(2)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0]['row'])->toBe(3)
        ->and($result['errors'][0]['email'])->toBe('not-an-email');

    expect(EmailCampaignRecipient::where('email_campaign_id', $this->campaign->id)->count())->toBe(1);
});

it('upserts existing recipients by email instead of duplicating', function () {
    EmailCampaignRecipient::factory()->for($this->campaign, 'campaign')->create([
        'email' => 'alice@example.com',
        'user_name' => 'Old name',
    ]);

    $result = $this->action->execute($this->campaign, [
        ['email' => 'alice@example.com', 'user_name' => 'New name', 'external_id' => 'A-9'],
    ]);

    expect($result['imported'])->toBe(0)
        ->and($result['updated'])->toBe(1);

    $recipients = EmailCampaignRecipient::where('email', 'alice@example.com')->get();
    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->user_name)->toBe('New name')
        ->and($recipients->first()->external_id)->toBe('A-9');
});

it('collapses duplicate emails within one file (last row wins)', function () {
    $result = $this->action->execute($this->campaign, [
        ['email' => 'dup@example.com', 'user_name' => 'First'],
        ['email' => 'DUP@example.com', 'user_name' => 'Second'],
    ]);

    expect($result['imported'])->toBe(1)
        ->and($result['updated'])->toBe(0);

    $recipients = EmailCampaignRecipient::where('email_campaign_id', $this->campaign->id)->get();
    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->user_name)->toBe('Second');
});

it('matches reserved headers case-insensitively', function () {
    $result = $this->action->execute($this->campaign, [
        ['Email' => 'carol@example.com', 'User_Name' => 'Carol', 'External_ID' => 'C-3'],
    ]);

    expect($result['imported'])->toBe(1);

    $carol = EmailCampaignRecipient::where('email', 'carol@example.com')->first();
    expect($carol->user_name)->toBe('Carol')
        ->and($carol->external_id)->toBe('C-3')
        ->and($carol->payload_json)->toBeNull();
});
