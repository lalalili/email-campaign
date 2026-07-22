<?php

namespace Lalalili\EmailCampaign\Contracts;

use Carbon\CarbonImmutable;
use Lalalili\EmailCampaign\Models\EmailCampaign;

interface EmailDeliveryWindow
{
    public function nextAllowedAt(EmailCampaign $campaign): ?CarbonImmutable;
}
