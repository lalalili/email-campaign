<?php

use Illuminate\Support\Facades\Queue;
use Lalalili\EmailCampaign\Actions\ScheduleDueCampaignsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

it('dispatches jobs only for due scheduled campaigns', function () {
    Queue::fake();

    $due = EmailCampaign::factory()->create([
        'status'       => EmailCampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    $future = EmailCampaign::factory()->create([
        'status'       => EmailCampaignStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);

    $draft = EmailCampaign::factory()->create([
        'status'       => EmailCampaignStatus::Draft,
        'scheduled_at' => now()->subMinute(),
    ]);

    // Add one recipient to $due so a job is dispatched
    EmailCampaignRecipient::factory()->create([
        'email_campaign_id' => $due->id,
    ]);

    app(ScheduleDueCampaignsAction::class)->execute();

    Queue::assertPushed(SendCampaignEmailJob::class, 1);

    expect($due->fresh()->status)->toBe(EmailCampaignStatus::Sending);
    expect($future->fresh()->status)->toBe(EmailCampaignStatus::Scheduled);
    expect($draft->fresh()->status)->toBe(EmailCampaignStatus::Draft);
});
