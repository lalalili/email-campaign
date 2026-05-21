<?php

namespace Lalalili\EmailCampaign\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Models\EmailDelivery;

class CampaignEmailSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public EmailDelivery $delivery) {}
}
