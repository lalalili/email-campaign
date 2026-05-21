<?php

namespace Lalalili\EmailCampaign\Contracts;

use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

interface VariableProvider
{
    /**
     * Return a flat key→value map of template variables for this recipient.
     * Later providers overwrite earlier ones; values must be scalar or null.
     *
     * @return array<string, scalar|null>
     */
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array;
}
