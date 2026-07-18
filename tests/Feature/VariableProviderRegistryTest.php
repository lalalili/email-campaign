<?php

use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Providers\RecipientVariableProvider;
use Lalalili\EmailCampaign\Providers\SystemVariableProvider;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;

it('describes available variables from describable providers', function () {
    $registry = new VariableProviderRegistry(app());
    $registry->register(SystemVariableProvider::class);
    $registry->register(RecipientVariableProvider::class);

    $descriptors = $registry->describe();
    $keys = array_column($descriptors, 'key');

    expect($keys)->toContain('campaign_name', 'now', 'today', 'email', 'user_name', 'external_id')
        ->and($descriptors[0])->toHaveKeys(['key', 'label']);
});

it('ignores providers that do not implement DescribableVariableProvider', function () {
    $registry = new VariableProviderRegistry(app());
    $registry->register(new class implements VariableProvider
    {
        public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
        {
            return ['undocumented' => 'x'];
        }
    });

    expect($registry->describe())->toBe([]);
});

it('deduplicates descriptors by key keeping the last', function () {
    $registry = new VariableProviderRegistry(app());
    $registry->register(SystemVariableProvider::class);
    $registry->register(SystemVariableProvider::class);

    $keys = array_column($registry->describe(), 'key');

    expect(array_count_values($keys)['campaign_name'])->toBe(1);
});
