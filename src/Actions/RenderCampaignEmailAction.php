<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Data\RenderedEmail;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class RenderCampaignEmailAction
{
    public function __construct(
        private BuildVariableMapAction $buildMap,
        private RenderEmailTemplateAction $render,
    ) {}

    public function execute(EmailCampaign $campaign, EmailCampaignRecipient $recipient): RenderedEmail
    {
        $variables = $this->buildMap->execute($campaign, $recipient);
        $missing = [];

        $subject = $this->render->execute($campaign->subject_template, $variables, false, $missing);
        $html = $campaign->html_template !== null
            ? $this->render->execute($campaign->html_template, $variables, true, $missing, ['survey_url'])
            : null;
        $text = $campaign->text_template !== null
            ? $this->render->execute($campaign->text_template, $variables, false, $missing)
            : null;

        return new RenderedEmail(
            subject: $subject,
            html: $html,
            text: $text,
            missingVariables: array_values(array_unique($missing)),
        );
    }
}
