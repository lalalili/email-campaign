<?php

namespace Lalalili\EmailCampaign\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;

/**
 * @property int $id
 * @property int $email_campaign_id
 * @property int $email_campaign_recipient_id
 * @property EmailDeliveryStatus $status
 * @property CarbonImmutable|null $sent_at
 * @property string|null $error_message
 * @property string|null $rendered_subject
 * @property string|null $tracking_token
 * @property string|null $to_email
 * @property CarbonImmutable|null $opened_at
 * @property-read EmailCampaign|null $campaign
 * @property-read EmailCampaignRecipient|null $recipient
 * @property-read Collection<int, EmailEvent> $events
 */
class EmailDelivery extends Model
{
    protected $fillable = [
        'email_campaign_id',
        'email_campaign_recipient_id',
        'status',
        'sent_at',
        'error_message',
        'rendered_subject',
        'tracking_token',
        'opened_at',
        'to_email',
    ];

    public static function generateTrackingToken(): string
    {
        return Str::random(48);
    }

    protected function casts(): array
    {
        return [
            'status'    => EmailDeliveryStatus::class,
            'sent_at'   => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<EmailCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    /**
     * @return BelongsTo<EmailCampaignRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(EmailCampaignRecipient::class, 'email_campaign_recipient_id');
    }

    /**
     * @return HasMany<EmailEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class, 'delivery_id');
    }
}
