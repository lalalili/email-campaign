<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailEventType;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailEvent;
use Lalalili\EmailCampaign\Models\EmailSuppression;

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

        if ($type === EmailEventType::Open && $delivery->opened_at === null) {
            $delivery->update(['opened_at' => now()]);
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
}
