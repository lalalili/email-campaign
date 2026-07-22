<?php

namespace Lalalili\EmailCampaign\Support;

use Carbon\CarbonImmutable;
use Lalalili\EmailCampaign\Contracts\EmailDeliveryWindow;
use Lalalili\EmailCampaign\Models\EmailCampaign;

class AllowAllEmailDeliveryWindow implements EmailDeliveryWindow
{
    public function nextAllowedAt(EmailCampaign $campaign): ?CarbonImmutable
    {
        return null;
    }
}
