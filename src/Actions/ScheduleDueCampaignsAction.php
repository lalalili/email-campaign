<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Jobs\DispatchCampaignJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class ScheduleDueCampaignsAction
{
    /**
     * 只負責掃描到期活動並逐一丟進佇列；實際名單同步與寄送在
     * DispatchCampaignJob 內執行，避免大型活動阻塞 schedule:run。
     * 同一活動被重複入隊時由 SendCampaignAction 的原子認領擋下。
     */
    public function execute(): void
    {
        EmailCampaign::query()
            ->where('status', EmailCampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->pluck('id')
            ->each(fn (int $campaignId) => DispatchCampaignJob::dispatch($campaignId));
    }

    public function __invoke(): void
    {
        $this->execute();
    }
}
