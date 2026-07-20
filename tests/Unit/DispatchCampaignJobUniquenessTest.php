<?php

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lalalili\EmailCampaign\Jobs\DispatchCampaignJob;

/**
 * ScheduleDueCampaignsAction 每分鐘掃一次到期活動，只要活動還是 Scheduled
 * 就再入隊一個 job，完全不看上一批是否還沒被消化。SendCampaignAction 的原子
 * 認領只在「執行時」擋下重複派發，佇列裡照樣會堆積。
 *
 * 2026-07-20 staging 因此在數十分鐘內累積出三萬多個 DispatchCampaignJob。
 * ShouldBeUnique 是防止佇列爆量的那道結構性保護，不能被拿掉。
 */
it('is queued as a unique job so the scheduler cannot stack duplicates', function (): void {
    expect(new DispatchCampaignJob(1))->toBeInstanceOf(ShouldBeUnique::class);
});

it('scopes the uniqueness lock to the campaign', function (): void {
    expect((new DispatchCampaignJob(42))->uniqueId())->toBe('42')
        ->and((new DispatchCampaignJob(43))->uniqueId())->toBe('43');
});

it('holds the uniqueness lock for longer than the job may run', function (): void {
    $job = new DispatchCampaignJob(1);

    // 鎖比 timeout 早過期的話，job 還在跑就會放行下一個，等於沒有保護。
    expect($job->uniqueFor)->toBeGreaterThan($job->timeout);
});
