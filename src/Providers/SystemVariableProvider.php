<?php

namespace Lalalili\EmailCampaign\Providers;

use Lalalili\EmailCampaign\Contracts\DescribableVariableProvider;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class SystemVariableProvider implements DescribableVariableProvider, VariableProvider
{
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        return [
            'campaign_name' => $campaign->name,
            'now' => now()->toDateTimeString(),
            'today' => now()->toDateString(),
        ];
    }

    public function availableVariables(): array
    {
        return [
            ['key' => 'campaign_name', 'label' => '活動名稱'],
            ['key' => 'now', 'label' => '目前時間'],
            ['key' => 'today', 'label' => '今日日期'],
        ];
    }
}
