<?php

namespace Lalalili\EmailCampaign\Actions;

use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;

class BuildVariableMapAction
{
    public function __construct(private VariableProviderRegistry $registry)
    {
    }

    /**
     * Build the merged variable map for one recipient.
     * extras_json maps source audience-list columns to campaign template keywords.
     *
     * @return array<string, mixed>
     */
    public function execute(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        $variables = $this->registry->collect($campaign, $recipient);

        // extras_json stores campaign-level personalization mappings:
        // [{ source: "車牌號碼", keyword: "number" }]
        if (! empty($campaign->extras_json)) {
            foreach ($this->personalizationMappings($campaign->extras_json) as $source => $keyword) {
                if (array_key_exists($source, $recipient->payload_json ?? [])) {
                    $variables[$keyword] = $recipient->payload_json[$source];
                } elseif (array_key_exists($source, $variables)) {
                    $variables[$keyword] = $variables[$source];
                }
            }
        }

        return $variables;
    }

    /**
     * @param  array<mixed>  $mappings
     * @return array<string, string>
     */
    private function personalizationMappings(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            $source = trim((string) ($mapping['source'] ?? ''));
            $keyword = trim((string) ($mapping['keyword'] ?? ''));

            if ($source === '' || $keyword === '') {
                continue;
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $keyword)) {
                continue;
            }

            $normalized[$source] = $keyword;
        }

        return $normalized;
    }
}
