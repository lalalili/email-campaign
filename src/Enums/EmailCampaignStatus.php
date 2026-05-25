<?php

namespace Lalalili\EmailCampaign\Enums;

enum EmailCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => '草稿',
            self::Scheduled => '已排程',
            self::Sending   => '寄送中',
            self::Sent      => '已寄出',
            self::Failed    => '失敗',
        };
    }
}
