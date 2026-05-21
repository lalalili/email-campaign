<?php

namespace Lalalili\EmailCampaign\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

/**
 * @extends Factory<EmailCampaignRecipient>
 */
class EmailCampaignRecipientFactory extends Factory
{
    protected $model = EmailCampaignRecipient::class;

    public function definition(): array
    {
        return [
            'email_campaign_id' => EmailCampaign::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'user_name' => $this->faker->name(),
            'external_id' => null,
            'payload_json' => null,
        ];
    }
}
