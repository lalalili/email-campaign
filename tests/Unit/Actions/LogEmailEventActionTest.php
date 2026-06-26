<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\EmailCampaign\Actions\LogEmailEventAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Listeners\HandleSurveyInvitationDispatched;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\SurveyCore\Enums\SurveyRecipientStatus;
use Lalalili\SurveyCore\Models\SurveyRecipient;

beforeEach(function (): void {
    // 最小化 survey_recipients 表：只為驗證開信回寫，不載入整套 survey-core 遷移。
    Schema::create('survey_recipients', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('survey_id');
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('external_id')->nullable();
        $table->json('payload_json')->nullable();
        $table->string('status')->default('active');
        $table->boolean('is_test')->default(false);
        $table->timestamp('invitation_opened_at')->nullable();
        $table->timestamps();
    });
});

function makeInvitationDelivery(SurveyRecipient $surveyRecipient): EmailDelivery
{
    $campaign = EmailCampaign::create([
        'survey_id' => $surveyRecipient->survey_id,
        'name' => HandleSurveyInvitationDispatched::INVITATION_CAMPAIGN_NAME,
        'subject_template' => 'subject',
        'html_template' => '<p>hi</p>',
        'status' => EmailCampaignStatus::Sending,
    ]);

    $campaignRecipient = EmailCampaignRecipient::create([
        'email_campaign_id' => $campaign->id,
        'external_id' => (string) $surveyRecipient->id,
        'email' => (string) $surveyRecipient->email,
    ]);

    return EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $campaignRecipient->id,
        'to_email' => (string) $surveyRecipient->email,
        'status' => EmailDeliveryStatus::Sent,
        'tracking_token' => EmailDelivery::generateTrackingToken(),
    ]);
}

it('marks the survey recipient invitation as opened on the first open event', function (): void {
    $surveyRecipient = SurveyRecipient::create([
        'survey_id' => 1,
        'email' => 'invitee@example.com',
        'status' => SurveyRecipientStatus::Active,
    ]);

    $delivery = makeInvitationDelivery($surveyRecipient);

    app(LogEmailEventAction::class)->execute($delivery, EmailEventType::Open);

    $surveyRecipient->refresh();
    expect($surveyRecipient->invitation_opened_at)->not->toBeNull()
        ->and($delivery->refresh()->opened_at)->not->toBeNull();

    // 第二次開信不覆寫首次開信時間
    $firstOpenedAt = $surveyRecipient->invitation_opened_at;
    $this->travel(10)->minutes();
    app(LogEmailEventAction::class)->execute($delivery, EmailEventType::Open);

    expect($surveyRecipient->refresh()->invitation_opened_at->equalTo($firstOpenedAt))->toBeTrue();
});

it('does not touch survey recipients for non-invitation campaigns', function (): void {
    $surveyRecipient = SurveyRecipient::create([
        'survey_id' => 1,
        'email' => 'invitee@example.com',
        'status' => SurveyRecipientStatus::Active,
    ]);

    $campaign = EmailCampaign::create([
        'name' => 'Newsletter',
        'subject_template' => 'subject',
        'html_template' => '<p>hi</p>',
        'status' => EmailCampaignStatus::Sending,
    ]);

    $campaignRecipient = EmailCampaignRecipient::create([
        'email_campaign_id' => $campaign->id,
        'external_id' => (string) $surveyRecipient->id,
        'email' => (string) $surveyRecipient->email,
    ]);

    $delivery = EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $campaignRecipient->id,
        'to_email' => (string) $surveyRecipient->email,
        'status' => EmailDeliveryStatus::Sent,
        'tracking_token' => EmailDelivery::generateTrackingToken(),
    ]);

    app(LogEmailEventAction::class)->execute($delivery, EmailEventType::Open);

    expect($surveyRecipient->refresh()->invitation_opened_at)->toBeNull();
});
