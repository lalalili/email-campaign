<?php

use Illuminate\Support\Facades\Event;
use Lalalili\EmailCampaign\Listeners\HandleSurveyInvitationDispatched;
use Lalalili\SurveyCore\Events\SurveyInvitationDispatched;

it('registers the survey invitation listener when survey core is installed', function () {
    $listeners = Event::getListeners(SurveyInvitationDispatched::class);

    expect($listeners)->not->toBeEmpty();

    $listenerNames = collect($listeners)
        ->map(function (Closure $listener): string {
            $reflection = new ReflectionFunction($listener);
            $staticVariables = $reflection->getStaticVariables();

            return (string) ($staticVariables['listener'] ?? '');
        });

    expect($listenerNames)->toContain(HandleSurveyInvitationDispatched::class);
});
