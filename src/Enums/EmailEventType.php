<?php

namespace Lalalili\EmailCampaign\Enums;

enum EmailEventType: string
{
    case Open = 'open';
    case Click = 'click';
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Unsubscribe = 'unsubscribe';
}
