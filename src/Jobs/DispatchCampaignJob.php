<?php

namespace Lalalili\EmailCampaign\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * 把整個活動的名單同步與逐封入隊移出 schedule:run 程序，
 * 避免大型活動阻塞排程 tick。重複派發由 SendCampaignAction 的原子認領擋下。
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** 失敗不自動重試：活動會轉 Failed，由使用者透過「重新寄送」重跑。 */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $campaignId)
    {
        $connection = config('email-campaign.queue.connection');

        if (is_string($connection) && $connection !== '' && $connection !== 'default') {
            $this->onConnection($connection);
        }

        $this->onQueue(config('email-campaign.queue.name'));
    }

    public function handle(SendCampaignAction $sendCampaign): void
    {
        $campaign = EmailCampaign::find($this->campaignId);

        if ($campaign !== null) {
            $sendCampaign->execute($campaign);
        }
    }
}
