<?php

namespace Lalalili\EmailCampaign\Providers;

use Lalalili\EmailCampaign\Contracts\DescribableVariableProvider;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\MarketingAutomation\Actions\IssueDispatchShortLinkAction;
use Lalalili\MarketingAutomation\Models\ActivityDispatch;

/**
 * Provides {{short_survey_url}} by wrapping the personalised survey URL
 * through the activity short link system.
 *
 * Requires the recipient to have been created by EmailChannelDispatcher
 * (which stores email_campaign_recipient_id in ActivityDispatch.external_response_json).
 */
class ShortSurveyUrlVariableProvider implements DescribableVariableProvider, VariableProvider
{
    public function __construct(private IssueDispatchShortLinkAction $issueShortLink) {}

    public function availableVariables(): array
    {
        return [
            ['key' => 'short_survey_url', 'label' => '問卷短連結'],
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        if (! $campaign->survey_id) {
            return [];
        }

        $dispatch = ActivityDispatch::where(
            'external_response_json->email_campaign_recipient_id',
            $recipient->id,
        )->first();

        if (! $dispatch) {
            return [];
        }

        $link = $this->issueShortLink->execute($dispatch);

        return [
            'short_survey_url' => $link->shortUrl->default_short_url,
        ];
    }
}
