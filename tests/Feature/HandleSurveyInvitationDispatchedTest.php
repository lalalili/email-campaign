<?php

use Illuminate\Support\Facades\Queue;
use Lalalili\EmailCampaign\Jobs\SendCampaignEmailJob;
use Lalalili\EmailCampaign\Listeners\HandleSurveyInvitationDispatched;
use Lalalili\SurveyCore\Events\SurveyInvitationDispatched;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyRecipient;
use Lalalili\SurveyCore\Models\SurveyToken;

it('ignores survey invitations when the survey email campaign integration is disabled', function () {
    Queue::fake();

    config()->set('survey-core.integrations.email_campaign.enabled', false);

    $survey = new Survey(['title' => 'Disabled Integration Survey']);
    $survey->id = 123;

    $recipient = new SurveyRecipient([
        'email' => 'recipient@example.com',
        'payload_json' => ['name' => 'Recipient'],
    ]);
    $recipient->id = 456;
    $recipient->setRelation('survey', $survey);

    $token = new SurveyToken(['token' => 'token-value']);

    (new HandleSurveyInvitationDispatched())->handle(
        new SurveyInvitationDispatched($recipient, $token, 'https://example.com/surveys/token-value'),
    );

    Queue::assertNotPushed(SendCampaignEmailJob::class);
});
