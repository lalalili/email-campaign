<?php

namespace Lalalili\EmailCampaign\Actions;

use Illuminate\Mail\Mailable;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaign\Models\EmailSuppression;
use Lalalili\EmailCampaign\Support\MailerFactory;

class SendTransactionalEmailAction
{
    public function __construct(
        private MailerFactory $mailerFactory,
        private InjectEmailTrackingAction $injectTracking,
    ) {
    }

    /**
     * Send a one-off transactional email from an existing Mailable with delivery tracking.
     *
     * @param  string[]  $to
     */
    public function executeWithMailable(
        array $to,
        Mailable $mailable,
        bool $checkSuppression = true,
        ?EmailSmtpProfile $smtpProfile = null,
    ): void {
        $subject = (string) $mailable->subject;
        $html = $mailable->render();

        $this->execute($to, $subject, $html, checkSuppression: $checkSuppression, smtpProfile: $smtpProfile);
    }

    /**
     * Send a one-off transactional email with delivery tracking.
     *
     * @param  string[]  $to
     */
    public function execute(
        array $to,
        string $subject,
        string $html,
        ?string $text = null,
        bool $checkSuppression = true,
        ?EmailSmtpProfile $smtpProfile = null,
    ): void {
        $mailer = $this->mailerFactory->forProfile($smtpProfile);

        foreach ($to as $email) {
            $email = mb_strtolower(trim($email));

            if ($checkSuppression && EmailSuppression::isSuppressed($email)) {
                continue;
            }

            $trackingToken = EmailDelivery::generateTrackingToken();

            $delivery = EmailDelivery::create([
                'email_campaign_id' => null,
                'email_campaign_recipient_id' => null,
                'to_email' => $email,
                'status' => EmailDeliveryStatus::Pending,
                'rendered_subject' => $subject,
                'tracking_token' => $trackingToken,
            ]);

            $trackedHtml = $this->injectTracking->execute($html, $trackingToken, $email);

            if (! (bool) config('external-communications.enabled', true)) {
                $delivery->update([
                    'status' => EmailDeliveryStatus::Skipped,
                    'error_message' => 'Email delivery disabled by external communications setting.',
                ]);

                continue;
            }

            $mailable = new class ($subject, $trackedHtml, $text) extends Mailable {
                public function __construct(
                    private string $subj,
                    private string $htmlBody,
                    private ?string $textBody,
                ) {
                }

                public function build(): static
                {
                    $this->subject($this->subj)->html($this->htmlBody);

                    if ($this->textBody !== null) {
                        $this->text('email-campaign::mail.text', ['content' => $this->textBody]);
                    }

                    return $this;
                }
            };

            try {
                $mailer->to($email)->send($mailable);

                $delivery->update([
                    'status' => EmailDeliveryStatus::Sent,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            } catch (\Throwable $e) {
                $delivery->update([
                    'status' => EmailDeliveryStatus::Failed,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }
}
