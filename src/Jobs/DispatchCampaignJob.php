<?php

namespace Lalalili\EmailCampaign\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * 把整個活動的名單同步與逐封入隊移出 schedule:run 程序，
 * 避免大型活動阻塞排程 tick。
 *
 * SendCampaignAction 的原子認領只在「執行時」擋下重複派發，佇列裡照樣會堆積
 * 成千上萬個等待中的重複 job：ScheduleDueCampaignsAction 每分鐘掃一次，只要
 * 活動還是 Scheduled 就再入隊一個，完全不看上一批是否還沒被消化。
 * 2026-07-20 staging 因此累積出數萬個 DispatchCampaignJob。改為 ShouldBeUnique，
 * 同一活動在佇列中最多只會有一個待處理 job。
 */
class DispatchCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** 失敗不自動重試：活動會轉 Failed，由使用者透過「重新寄送」重跑。 */
    public int $tries = 1;

    public int $timeout = 1800;

    /** 唯一鎖的存活時間須大於 $timeout，否則 job 還在跑就會放行下一個。 */
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

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
