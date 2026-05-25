<?php

namespace Lalalili\EmailCampaign\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * @extends Factory<EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    public function definition(): array
    {
        return [
            'name'             => 'Campaign '.Str::random(8),
            'subject_template' => 'Hello {{ user_name }}',
            'html_template'    => '<p>Hi {{ user_name }}, welcome to {{ campaign_name }}.</p>',
            'text_template'    => null,
            'survey_id'        => null,
            'status'           => EmailCampaignStatus::Draft,
            'scheduled_at'     => null,
            'extras_json'      => null,
        ];
    }
}
