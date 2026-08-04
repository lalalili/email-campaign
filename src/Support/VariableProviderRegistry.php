<?php

namespace Lalalili\EmailCampaign\Support;

use Illuminate\Contracts\Container\Container;
use Lalalili\EmailCampaign\Contracts\DescribableVariableProvider;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class VariableProviderRegistry
{
    /** @var array<int, class-string<VariableProvider>|VariableProvider> */
    private array $providers = [];

    public function __construct(private Container $container)
    {
    }

    /** @param  class-string<VariableProvider>|VariableProvider  $provider */
    public function register(string|VariableProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return array<string, scalar|null> */
    public function collect(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        $variables = [];

        foreach ($this->providers as $provider) {
            $instance = is_string($provider) ? $this->container->make($provider) : $provider;
            $variables = array_merge($variables, $instance->variablesFor($campaign, $recipient));
        }

        return $variables;
    }

    /**
     * Aggregate the static variable descriptors advertised by providers that
     * implement {@see DescribableVariableProvider}, for the builder sidebar.
     * Later providers' duplicate keys overwrite earlier ones.
     *
     * @return list<array{key: string, label: string}>
     */
    public function describe(): array
    {
        $descriptors = [];

        foreach ($this->providers as $provider) {
            $instance = is_string($provider) ? $this->container->make($provider) : $provider;

            if (! $instance instanceof DescribableVariableProvider) {
                continue;
            }

            foreach ($instance->availableVariables() as $descriptor) {
                $descriptors[$descriptor['key']] = $descriptor;
            }
        }

        return array_values($descriptors);
    }
}
