<?php

use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Providers\ShortSurveyUrlVariableProvider;

it('returns no variables when marketing automation is unavailable', function () {
    $campaign = new EmailCampaign([
        'survey_id' => 123,
    ]);
    $recipient = new EmailCampaignRecipient([
        'id' => 456,
    ]);

    $provider = new ShortSurveyUrlVariableProvider;

    expect($provider->variablesFor($campaign, $recipient))->toBe([]);
});
