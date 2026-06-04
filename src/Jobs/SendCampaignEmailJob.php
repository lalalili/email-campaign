<?php

namespace Lalalili\EmailCampaign\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Actions\InjectEmailTrackingAction;
use Lalalili\EmailCampaign\Actions\RenderCampaignEmailAction;
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

    public int $backoff = 60;

    public function __construct(
        private EmailCampaign $campaign,
        private EmailCampaignRecipient $recipient,
    ) {
        $this->onConnection($this->configuredQueueConnection());
        $this->onQueue(config('email-campaign.queue.name'));
    }

    public function handle(RenderCampaignEmailAction $renderAction, MailerFactory $mailerFactory, InjectEmailTrackingAction $injectTracking): void
    {
        $recipientEmail = trim((string) $this->recipient->email);

        if ((bool) config('email-campaign.demo_safe_mode', false)) {
            EmailDelivery::updateOrCreate(
                [
                    'email_campaign_id'           => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status'        => EmailDeliveryStatus::Skipped,
                    'to_email'      => $recipientEmail !== '' ? $recipientEmail : null,
                    'error_message' => 'Email delivery disabled by demo safe mode.',
                ],
            );

            $this->checkCampaignCompletion();

            return;
        }

        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            EmailDelivery::updateOrCreate(
                [
                    'email_campaign_id'           => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status'        => EmailDeliveryStatus::Skipped,
                    'to_email'      => $recipientEmail !== '' ? $recipientEmail : null,
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
                    'email_campaign_id'           => $this->campaign->id,
                    'email_campaign_recipient_id' => $this->recipient->id,
                ],
                [
                    'status'   => EmailDeliveryStatus::Skipped,
                    'to_email' => $recipientEmail,
                ],
            );

            $this->checkCampaignCompletion();

            return;
        }

        $delivery = EmailDelivery::firstOrCreate(
            [
                'email_campaign_id'           => $this->campaign->id,
                'email_campaign_recipient_id' => $this->recipient->id,
            ],
            [
                'status'         => EmailDeliveryStatus::Pending,
                'tracking_token' => EmailDelivery::generateTrackingToken(),
                'to_email'       => $recipientEmail,
            ],
        );

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
                'status'           => EmailDeliveryStatus::Sent,
                'sent_at'          => now(),
                'rendered_subject' => $rendered->subject,
                'error_message'    => null,
            ]);

            CampaignEmailSent::dispatch($delivery);
        } catch (\Throwable $e) {
            $delivery->update([
                'status'        => EmailDeliveryStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            CampaignEmailFailed::dispatch($delivery, $e);

            throw $e;
        }

        $this->checkCampaignCompletion();
    }

    private function checkCampaignCompletion(): void
    {
        $campaign = $this->campaign->fresh();

        if (! $campaign) {
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
