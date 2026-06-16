<?php

namespace Lalalili\EmailCampaign\Providers;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

/**
 * Provides {{short_survey_url}} by wrapping the personalised survey URL
 * through the activity short link system.
 *
 * Requires the recipient to have been created by EmailChannelDispatcher
 * (which stores email_campaign_recipient_id in ActivityDispatch.external_response_json).
 */
class ShortSurveyUrlVariableProvider implements VariableProvider
{
    private const ISSUE_SHORT_LINK_ACTION_CLASS = 'Lalalili\\MarketingAutomation\\Actions\\IssueDispatchShortLinkAction';

    private const ACTIVITY_DISPATCH_CLASS = 'Lalalili\\MarketingAutomation\\Models\\ActivityDispatch';

    public function __construct(private ?Container $container = null) {}

    /**
     * @return array<string, scalar|null>
     */
    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        if (! $campaign->survey_id) {
            return [];
        }

        $activityDispatchClass = self::ACTIVITY_DISPATCH_CLASS;
        $issueShortLinkClass = self::ISSUE_SHORT_LINK_ACTION_CLASS;

        if (
            ! class_exists($activityDispatchClass)
            || ! is_subclass_of($activityDispatchClass, Model::class)
            || ! class_exists($issueShortLinkClass)
        ) {
            return [];
        }

        $dispatch = $activityDispatchClass::query()->where(
            'external_response_json->email_campaign_recipient_id',
            $recipient->id,
        )->first();

        if (! $dispatch instanceof Model) {
            return [];
        }

        $issueShortLink = ($this->container ?? Container::getInstance())->make($issueShortLinkClass);
        if (! is_object($issueShortLink) || ! method_exists($issueShortLink, 'execute')) {
            return [];
        }

        $link = $issueShortLink->execute($dispatch);
        $shortUrl = data_get($link, 'shortUrl.default_short_url');
        if (! is_scalar($shortUrl)) {
            return [];
        }

        return [
            'short_survey_url' => (string) $shortUrl,
        ];
    }
}
