<?php

namespace Lalalili\EmailCampaign\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lalalili\AudienceCore\Concerns\LogsModelActivity;
use Lalalili\EmailCampaign\Database\Factories\EmailCampaignRecipientFactory;

/**
 * @property int $id
 * @property int $email_campaign_id
 * @property int|null $audience_list_row_id
 * @property string $email
 * @property string|null $user_name
 * @property string|null $external_id
 * @property array<string, mixed>|null $payload_json
 * @property-read EmailCampaign $campaign
 * @property-read EmailDelivery|null $delivery
 */
class EmailCampaignRecipient extends Model
{
    /** @use HasFactory<EmailCampaignRecipientFactory> */
    use HasFactory;

    use LogsModelActivity;

    /** @var list<string> 匯入建立為批次資料同步，僅記錄管理者編輯與刪除 */
    protected static array $recordEvents = ['updated', 'deleted'];

    protected static function newFactory(): EmailCampaignRecipientFactory
    {
        return EmailCampaignRecipientFactory::new();
    }

    protected $fillable = [
        'email_campaign_id',
        'audience_list_row_id',
        'email',
        'user_name',
        'external_id',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // sqlsrv 上 deliveries.email_campaign_recipient_id FK 為 NO ACTION
        // （multiple cascade paths 限制），刪收件人前先清 deliveries；
        // 其他 driver 有 DB cascade，重複刪除無害。
        static::deleting(function (self $recipient): void {
            $recipient->delivery()->delete();
        });
    }

    /**
     * @return BelongsTo<EmailCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    /**
     * @return HasOne<EmailDelivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(EmailDelivery::class, 'email_campaign_recipient_id');
    }
}
