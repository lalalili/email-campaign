<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Listeners\HandleSurveyInvitationDispatched;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailEvent;
use Lalalili\EmailCampaign\Models\EmailSuppression;
use Lalalili\SurveyCore\Models\SurveyRecipient;

class LogEmailEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(EmailDelivery $delivery, EmailEventType $type, ?string $url = null, array $payload = []): EmailEvent
    {
        $event = EmailEvent::create([
            'delivery_id' => $delivery->id,
            'type' => $type,
            'url' => $url,
            'occurred_at' => now(),
            'payload_json' => $payload ?: null,
        ]);

        if ($type === EmailEventType::Open) {
            $delivery->increment('open_count');

            if ($delivery->opened_at === null) {
                $delivery->update(['opened_at' => now()]);
            }

            $this->markSurveyInvitationOpened($delivery);
        } elseif ($type === EmailEventType::Click) {
            $delivery->increment('click_count');
        }

        if (in_array($type, [EmailEventType::Unsubscribe, EmailEventType::Bounce, EmailEventType::Complaint], true)) {
            $email = $delivery->to_email ?? $delivery->recipient?->email;

            if ($email) {
                EmailSuppression::firstOrCreate(
                    ['email' => mb_strtolower($email)],
                    [
                        'reason' => $type->value,
                        'source_delivery_id' => $delivery->id,
                        'suppressed_at' => now(),
                    ],
                );
            }
        }

        return $event;
    }

    /**
     * 邀請信首次開信時回寫 SurveyRecipient.invitation_opened_at。
     * survey-core 為軟相依（require-dev），未安裝時直接略過。
     */
    private function markSurveyInvitationOpened(EmailDelivery $delivery): void
    {
        if (! class_exists(SurveyRecipient::class)) {
            return;
        }

        $campaign = $delivery->campaign;

        if ($campaign === null
            || $campaign->survey_id === null
            || $campaign->name !== HandleSurveyInvitationDispatched::INVITATION_CAMPAIGN_NAME) {
            return;
        }

        $externalId = $delivery->recipient?->external_id;

        if ($externalId === null || ! ctype_digit((string) $externalId)) {
            return;
        }

        SurveyRecipient::query()
            ->whereKey((int) $externalId)
            ->whereNull('invitation_opened_at')
            ->update(['invitation_opened_at' => now()]);
    }
}
