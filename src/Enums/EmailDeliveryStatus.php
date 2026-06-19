<?php

namespace Lalalili\EmailCampaign\Enums;

enum EmailDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待寄',
            self::Sent => '已寄',
            self::Failed => '失敗',
            self::Skipped => '略過',
        };
    }
}
