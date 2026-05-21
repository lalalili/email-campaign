<?php

use Illuminate\Container\Container;
use Lalalili\EmailCampaign\Actions\BuildVariableMapAction;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Providers\RecipientVariableProvider;
use Lalalili\EmailCampaign\Providers\SystemVariableProvider;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;

function makeRecipient(array $attrs = []): EmailCampaignRecipient
{
    return new EmailCampaignRecipient($attrs);
}

function makeCampaign(array $attrs = []): EmailCampaign
{
    return new EmailCampaign(array_merge([
        'name' => 'Test campaign',
        'subject_template' => 'Hello {{ user_name }}',
        'html_template' => '<p>Hi {{ user_name }}</p>',
        'extras_json' => null,
    ], $attrs));
}

function makeVariableRegistry(): VariableProviderRegistry
{
    $registry = new VariableProviderRegistry(new Container);

    $registry->register(SystemVariableProvider::class);
    $registry->register(RecipientVariableProvider::class);

    return $registry;
}

it('merges variables from all providers in order', function () {
    $campaign = makeCampaign(['extras_json' => null]);
    $recipient = makeRecipient(['email_campaign_id' => $campaign->id]);

    $registry = makeVariableRegistry();

    $registry->register(new class implements VariableProvider
    {
        public function variablesFor(EmailCampaign $c, EmailCampaignRecipient $r): array
        {
            return ['a' => 'first', 'b' => 'from_first'];
        }
    });

    $registry->register(new class implements VariableProvider
    {
        public function variablesFor(EmailCampaign $c, EmailCampaignRecipient $r): array
        {
            return ['b' => 'from_second', 'c' => 'third'];
        }
    });

    $action = new BuildVariableMapAction($registry);
    $map = $action->execute($campaign, $recipient);

    expect($map['a'])->toBe('first')
        ->and($map['b'])->toBe('from_second')
        ->and($map['c'])->toBe('third');
});

it('maps audience list fields to template keywords from personalization mappings', function () {
    $campaign = makeCampaign([
        'extras_json' => [
            ['source' => '車牌號碼', 'keyword' => 'number'],
            ['source' => '服務據點', 'keyword' => 'service_center'],
        ],
    ]);
    $recipient = makeRecipient([
        'email_campaign_id' => $campaign->id,
        'payload_json' => [
            '車牌號碼' => 'ABC-1234',
            '服務據點' => '台北服務中心',
        ],
    ]);

    $action = new BuildVariableMapAction(makeVariableRegistry());
    $map = $action->execute($campaign, $recipient);

    expect($map['number'])->toBe('ABC-1234')
        ->and($map['service_center'])->toBe('台北服務中心');
});

it('ignores invalid personalization mapping keywords', function () {
    $campaign = makeCampaign([
        'extras_json' => [
            ['source' => '車牌號碼', 'keyword' => '123number'],
        ],
    ]);
    $recipient = makeRecipient([
        'email_campaign_id' => $campaign->id,
        'payload_json' => ['車牌號碼' => 'ABC-1234'],
    ]);

    $action = new BuildVariableMapAction(makeVariableRegistry());
    $map = $action->execute($campaign, $recipient);

    expect($map)->not->toHaveKey('123number');
});
