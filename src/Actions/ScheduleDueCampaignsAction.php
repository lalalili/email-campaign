<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class ScheduleDueCampaignsAction
{
    public function __construct(private SendCampaignAction $sendCampaign) {}

    public function execute(): void
    {
        EmailCampaign::query()
            ->where('status', EmailCampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->each(fn (EmailCampaign $campaign) => $this->sendCampaign->execute($campaign));
    }

    public function __invoke(): void
    {
        $this->execute();
    }
}
