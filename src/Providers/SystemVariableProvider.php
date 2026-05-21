<?php

namespace Lalalili\EmailCampaign\Providers;

use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class SystemVariableProvider implements VariableProvider
{
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        return [
            'campaign_name' => $campaign->name,
            'now' => now()->toDateTimeString(),
            'today' => now()->toDateString(),
        ];
    }
}
