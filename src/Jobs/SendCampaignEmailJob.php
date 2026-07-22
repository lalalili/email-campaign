<?php

namespace Lalalili\EmailCampaign\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
use Lalalili\EmailCampaign\Contracts\EmailDeliveryWindow;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Events\CampaignCompleted;
use Lalalili\EmailCampaign\Events\CampaignEmailFailed;
use Lalalili\EmailCampaign\Events\CampaignEmailSent;
use Lalalili\EmailCampaign\Mail\CampaignMail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailSuppression;
use Lalalili\EmailCampaign\Support\MailerFactory;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** SMTP 連線卡住時的保護：單封寄送不應超過 2 分鐘。 */
    public int $timeout = 120;

    public int $backoff = 60;

    public function __construct(
        private EmailCampaign $campaign,
        private EmailCampaignRecipient $recipient,
        int $maxAttempts = 3,
    ) {
        $this->tries = $maxAttempts;
        $this->onConnection($this->configuredQueueConnection());
        $this->onQueue(config('email-campaign.queue.name'));
    }

    /**
     * 未設 rate_limit.max_per_minute 時不套用；設定後超過上限的 job 會被 release 回
     * 佇列稍後重試（受 $tries 上限約束，過低的上限搭配高流量可能耗盡重試，應依 worker
     * 吞吐調整上限）。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if (! config('email-campaign.rate_limit.max_per_minute')) {
            return [];
        }

        return [new RateLimited('email-campaign-send')];
    }

    public function handle(
        RenderCampaignEmailAction $renderAction,
        MailerFactory $mailerFactory,
        InjectEmailTrackingAction $injectTracking,
        EmailDeliveryWindow $deliveryWindow,
    ): void {
        $recipientEmail = trim((string) $this->recipient->email);

        $demoSafeMode = (bool) config('email-campaign.demo_safe_mode', false);

        if ($demoSafeMode || ! (bool) config('external-communications.enabled', true)) {
            EmailDelivery::updateOrCreate(
                [
                    'email_campaign_id' => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status' => EmailDeliveryStatus::Skipped,
                    'to_email' => $recipientEmail !== '' ? $recipientEmail : null,
                    'error_message' => $demoSafeMode
                        ? 'Email delivery disabled by demo safe mode.'
                        : 'Email delivery disabled by external communications setting.',
                ],
            );

            $this->checkCampaignCompletion();

            return;
        }

        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            EmailDelivery::updateOrCreate(
                [
                    'email_campaign_id' => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status' => EmailDeliveryStatus::Skipped,
                    'to_email' => $recipientEmail !== '' ? $recipientEmail : null,
                    'error_message' => 'Recipient email is missing or invalid.',
                ],
            );

            $this->checkCampaignCompletion();

            return;
        }

        // Skip suppressed addresses
        if (EmailSuppression::isSuppressed($recipientEmail)) {
            EmailDelivery::updateOrCreate(
                [
                    'email_campaign_id' => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status' => EmailDeliveryStatus::Skipped,
                    'to_email' => $recipientEmail,
                ],
            );

            $this->checkCampaignCompletion();

            return;
        }

        $delivery = EmailDelivery::firstOrCreate(
            [
                'email_campaign_id' => $this->campaign->id,
                'email_campaign_recipient_id' => $this->recipient->id,
            ],
            [
                'status' => EmailDeliveryStatus::Pending,
                'tracking_token' => EmailDelivery::generateTrackingToken(),
                'to_email' => $recipientEmail,
            ],
        );

        // 已寄出的收件人不可重寄：寄送成功後若後續步驟（事件、完成檢查）拋錯觸發重試，
        // 或同一 (campaign, recipient) 被併發派發兩個 job，這裡擋下第二次真實寄送。
        if ($delivery->status === EmailDeliveryStatus::Sent) {
            $this->checkCampaignCompletion();

            return;
        }

        $nextAllowedAt = $deliveryWindow->nextAllowedAt($this->campaign);

        if ($nextAllowedAt !== null) {
            $remainingAttempts = max(1, $this->tries - $this->attempts() + 1);

            self::dispatch($this->campaign, $this->recipient, $remainingAttempts)
                ->delay($nextAllowedAt);

            return;
        }

        $trackingToken = $delivery->tracking_token;

        // Ensure token exists for retried jobs
        if ($trackingToken === null) {
            $trackingToken = EmailDelivery::generateTrackingToken();
            $delivery->update(['tracking_token' => $trackingToken]);
        }

        try {
            $rendered = $renderAction->execute($this->campaign, $this->recipient);

            $html = $rendered->html !== null
                ? $injectTracking->execute($rendered->html, $trackingToken, $recipientEmail)
                : null;

            $mailer = $mailerFactory->forProfile($this->campaign->smtpProfile);
            $mailer->to($recipientEmail)->send(new CampaignMail($rendered->withHtml($html)));

            $delivery->update([
                'status' => EmailDeliveryStatus::Sent,
                'sent_at' => now(),
                'rendered_subject' => $rendered->subject,
                'error_message' => null,
            ]);

            CampaignEmailSent::dispatch($delivery);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => EmailDeliveryStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            CampaignEmailFailed::dispatch($delivery, $e);

            throw $e;
        }

        $this->checkCampaignCompletion();
    }

    /**
     * 永久失敗（重試耗盡）的 job 也代表該收件人已結算，補做 DECR 與完成檢查，
     * 避免活動因最後一名收件人永久失敗而卡在 sending。
     */
    public function failed(\Throwable $e): void
    {
        Redis::decr(self::remainingKey($this->campaign->id));

        self::finalizeIfComplete($this->campaign->id);
    }

    public static function remainingKey(int $campaignId): string
    {
        return "email-campaign:{$campaignId}:remaining";
    }

    private function checkCampaignCompletion(): void
    {
        // Redis 計數器僅作為「是否值得跑權威 SQL 檢查」的閘門，將每 job 雙 COUNT 收斂為結束時一次。
        // 漂移不影響正確性：完成判定一律以 SQL settled>=total 為準（見 finalizeIfComplete）。
        $remaining = Redis::decr(self::remainingKey($this->campaign->id));

        if ($remaining > 0) {
            return;
        }

        self::finalizeIfComplete($this->campaign->id);
    }

    /**
     * 權威完成判定：已結算的派送數 >= 收件人總數時，標記活動完成。
     * 供 job 與對帳排程共用；計數器只是觸發時機，此處才是正確性來源。
     */
    public static function finalizeIfComplete(int $campaignId): void
    {
        $campaign = EmailCampaign::find($campaignId);

        if (! $campaign || $campaign->status === EmailCampaignStatus::Sent) {
            return;
        }

        $totalRecipients = $campaign->recipients()->count();
        $settledDeliveries = $campaign->deliveries()
            ->whereIn('status', [EmailDeliveryStatus::Sent->value, EmailDeliveryStatus::Failed->value, EmailDeliveryStatus::Skipped->value])
            ->count();

        if ($settledDeliveries >= $totalRecipients) {
            $campaign->update(['status' => EmailCampaignStatus::Sent, 'sent_at' => now()]);
            CampaignCompleted::dispatch($campaign);
        }
    }

    private function configuredQueueConnection(): ?string
    {
        $connection = config('email-campaign.queue.connection');

        if ($connection === null || $connection === '' || $connection === 'default') {
            return null;
        }

        return (string) $connection;
    }
}
