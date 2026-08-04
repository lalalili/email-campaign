<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Data\RenderedEmail;
use Lalalili\EmailCampaign\Mail\CampaignMail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Support\MailerFactory;

/**
 * Renders a campaign with a sample recipient and sends a one-off preview to an
 * arbitrary test address, using the campaign's configured SMTP profile.
 *
 * No delivery / tracking rows are written — this is purely for previewing the
 * rendered subject and body and surfacing unresolved template variables.
 */
class SendTestCampaignEmailAction
{
    public function __construct(
        private RenderCampaignEmailAction $render,
        private MailerFactory $mailerFactory,
    ) {
    }

    /**
     * @return RenderedEmail the rendered preview (carries `missingVariables`)
     */
    public function execute(EmailCampaign $campaign, string $testEmail, ?EmailCampaignRecipient $sample = null): RenderedEmail
    {
        $recipient = $sample
            ?? $campaign->recipients()->first()
            ?? new EmailCampaignRecipient(['email_campaign_id' => $campaign->id, 'email' => $testEmail]);

        $rendered = $this->render->execute($campaign, $recipient);

        $preview = new RenderedEmail(
            subject: '[測試] '.$rendered->subject,
            html: $rendered->html,
            text: $rendered->text,
            missingVariables: $rendered->missingVariables,
        );

        if (! (bool) config('external-communications.enabled', true)) {
            return $rendered;
        }

        $this->mailerFactory->forProfile($campaign->smtpProfile)
            ->to($testEmail)
            ->send(new CampaignMail($preview));

        return $rendered;
    }
}
