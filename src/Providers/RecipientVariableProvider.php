<?php

namespace Lalalili\EmailCampaign\Providers;

use Lalalili\EmailCampaign\Contracts\DescribableVariableProvider;
use Lalalili\EmailCampaign\Contracts\VariableProvider;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class RecipientVariableProvider implements DescribableVariableProvider, VariableProvider
{
    /**
     * @var array<int, string>
     */
    private const RESERVED_PAYLOAD_KEYS = [
        'email',
        'user_name',
        'external_id',
    ];

    public function variablesFor(EmailCampaign $campaign, EmailCampaignRecipient $recipient): array
    {
        $vars = [
            'email' => $recipient->email,
            'user_name' => $recipient->user_name ?? '',
            'external_id' => $recipient->external_id ?? '',
        ];

        // Flatten payload_json fields; they overwrite core fields only if explicitly set
        if (! empty($recipient->payload_json)) {
            foreach ($recipient->payload_json as $key => $value) {
                $key = (string) $key;

                if (in_array($key, self::RESERVED_PAYLOAD_KEYS, true)) {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $vars[$key] = $value;
                }
            }
        }

        return $vars;
    }

    /**
     * 僅列出固定的收件人核心欄位；payload_json 的動態欄位因人而異，不在此靜態描述。
     */
    public function availableVariables(): array
    {
        return [
            ['key' => 'email', 'label' => '收件人 Email'],
            ['key' => 'user_name', 'label' => '收件人姓名'],
            ['key' => 'external_id', 'label' => '外部識別碼'],
        ];
    }
}
