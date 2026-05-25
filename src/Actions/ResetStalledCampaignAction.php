<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class ResetStalledCampaignAction
{
    public function execute(EmailCampaign $campaign): bool
    {
        if ($campaign->status !== EmailCampaignStatus::Sending) {
            return false;
        }

        if ($campaign->deliveries()->exists()) {
            return false;
        }

        return $campaign->update([
            'status'  => EmailCampaignStatus::Draft,
            'sent_at' => null,
        ]);
    }
}
