<?php

use Illuminate\Support\Facades\Queue;
use Lalalili\EmailCampaign\Actions\ScheduleDueCampaignsAction;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Jobs\DispatchCampaignJob;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

it('queues a dispatch job only for due scheduled campaigns', function () {
    Queue::fake();

    $due = EmailCampaign::factory()->create([
        'status' => EmailCampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);

    $future = EmailCampaign::factory()->create([
        'status' => EmailCampaignStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);

    $draft = EmailCampaign::factory()->create([
        'status' => EmailCampaignStatus::Draft,
        'scheduled_at' => now()->subMinute(),
    ]);

    app(ScheduleDueCampaignsAction::class)->execute();

    Queue::assertPushed(DispatchCampaignJob::class, 1);
    Queue::assertPushed(fn (DispatchCampaignJob $job): bool => $job->campaignId === $due->id);

    // 掃描階段不改狀態、不做名單同步；認領發生在 DispatchCampaignJob 內。
    expect($due->fresh()->status)->toBe(EmailCampaignStatus::Scheduled);
    expect($future->fresh()->status)->toBe(EmailCampaignStatus::Scheduled);
    expect($draft->fresh()->status)->toBe(EmailCampaignStatus::Draft);
});

it('runs the campaign dispatch inside the queued job', function () {
    Queue::fake([SendCampaignEmailJob::class]);

    $campaign = EmailCampaign::factory()->create([
        'status' => EmailCampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    EmailCampaignRecipient::factory()->create(['email_campaign_id' => $campaign->id]);

    (new DispatchCampaignJob($campaign->id))->handle(app(SendCampaignAction::class));

    Queue::assertPushed(SendCampaignEmailJob::class, 1);
    expect($campaign->fresh()->status)->toBe(EmailCampaignStatus::Sending);
});
