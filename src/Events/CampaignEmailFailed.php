<?php

namespace Lalalili\EmailCampaign\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lalalili\EmailCampaign\Models\EmailDelivery;

class CampaignEmailFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public EmailDelivery $delivery, public \Throwable $exception)
    {
    }
}
