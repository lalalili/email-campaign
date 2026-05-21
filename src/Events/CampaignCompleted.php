<?php

namespace Lalalili\EmailCampaign\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class CampaignCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public EmailCampaign $campaign) {}
}
