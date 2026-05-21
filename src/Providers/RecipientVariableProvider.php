<?php

namespace Lalalili\EmailCampaign\Providers;

use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class RecipientVariableProvider implements VariableProvider
{
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        $vars = [
            'email' => $recipient->email,
            'user_name' => $recipient->user_name ?? '',
            'external_id' => $recipient->external_id ?? '',
        ];

        // Flatten payload_json fields; they overwrite core fields only if explicitly set
        if (! empty($recipient->payload_json)) {
            foreach ($recipient->payload_json as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $vars[(string) $key] = $value;
                }
            }
        }

        return $vars;
    }
}
