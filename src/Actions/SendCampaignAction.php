<?php

namespace Lalalili\EmailCampaign\Actions;

use Illuminate\Support\Facades\Redis;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Events\CampaignDispatched;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class SendCampaignAction
{
    public function __construct(private readonly SyncAudienceListToCampaignRecipientsAction $syncAudienceList)
    {
    }

    /**
     * 派發整個活動的寄送任務。
     *
     * 以原子條件更新先「認領」活動（Draft/Scheduled/Failed → Sending），
     * 認領失敗代表另一個入口（Filament 按鈕或排程）已在處理，直接返回 false，
     * 避免同一活動被併發派發兩次造成全體收件人重複收信。
     */
    public function execute(EmailCampaign $campaign): bool
    {
        $claimed = EmailCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [
                EmailCampaignStatus::Draft,
                EmailCampaignStatus::Scheduled,
                EmailCampaignStatus::Failed,
            ])
            ->update(['status' => EmailCampaignStatus::Sending]);

        if ($claimed === 0) {
            return false;
        }

        $campaign->refresh();

        try {
            $this->syncAudienceList->execute($campaign);

            $total = $campaign->recipients()->count();

            if ($total === 0) {
                $campaign->update(['status' => EmailCampaignStatus::Failed]);

                return false;
            }

            // 完成檢查的「待結算」計數器：job 每結算一名收件人即 DECR，歸零才觸發權威 SQL 檢查。
            // 計數器漂移最多造成延後（由對帳排程補判），完成判定仍以 SQL settled>=total 為準。
            Redis::setex(SendCampaignEmailJob::remainingKey($campaign->id), 7 * 24 * 3600, $total);

            // 分批入隊取代一次性 hydrate 全部收件人（10 萬筆）。
            $campaign->recipients()
                ->select(['id', 'email_campaign_id'])
                ->chunkById(500, function ($recipients) use ($campaign): void {
                    $recipients->each(fn ($recipient) => SendCampaignEmailJob::dispatch($campaign, $recipient));
                });

            CampaignDispatched::dispatch($campaign);
        } catch (\Throwable $e) {
            $campaign->update(['status' => EmailCampaignStatus::Failed]);

            throw $e;
        }

        return true;
    }
}
