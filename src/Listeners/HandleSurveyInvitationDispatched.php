<?php

namespace Lalalili\EmailCampaign\Listeners;

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\SurveyCore\Events\SurveyInvitationDispatched;

/**
 * Bridges survey invitation into the email-campaign delivery pipeline so every
 * invitation gets an EmailDelivery tracking record and is sent via the
 * configured SMTP profile + queue.
 *
 * Each survey gets a single "invitation campaign" container (type=survey_invitation).
 * The container is created on first use and reused for subsequent sends.
 */
class HandleSurveyInvitationDispatched
{
    public function handle(SurveyInvitationDispatched $event): void
    {
        $recipient = $event->recipient;
        $survey = $recipient->survey;

        // Find or create the per-survey invitation campaign container.
        $campaign = EmailCampaign::firstOrCreate(
            ['survey_id' => $survey->id, 'name' => '__survey_invitation__'],
            [
                'description'      => "問卷邀請信容器：{$survey->title}",
                'subject_template' => '邀請您填寫問卷：{{ survey_title }}',
                'html_template'    => $this->defaultHtmlTemplate(),
                'text_template'    => '您好，請點擊以下連結填寫問卷：{{ survey_url }}',
                'status'           => EmailCampaignStatus::Sending,
            ],
        );

        // Create or refresh the campaign recipient row for this survey recipient.
        $campaignRecipient = EmailCampaignRecipient::updateOrCreate(
            [
                'email_campaign_id' => $campaign->id,
                'external_id'       => (string) $recipient->id,
            ],
            [
                'email'                => (string) $recipient->email,
                'audience_list_row_id' => $recipient->audience_list_row_id,
                'payload_json'         => $recipient->payload_json ?? [],
            ],
        );

        SendCampaignEmailJob::dispatch($campaign, $campaignRecipient);
    }

    private function defaultHtmlTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><title>問卷邀請</title></head>
<body style="font-family:system-ui,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#111827">
  <p>您好，</p>
  <p>誠摯邀請您填寫以下問卷：<strong>{{ survey_title }}</strong></p>
  <p style="margin:24px 0">
    <a href="{{ survey_url }}"
       style="background:#6366f1;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block">
      前往填寫問卷
    </a>
  </p>
  <p style="color:#6b7280;font-size:0.875rem">或複製連結：{{ survey_url }}</p>
</body>
</html>
HTML;
    }
}
