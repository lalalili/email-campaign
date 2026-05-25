<?php

namespace Lalalili\EmailCampaign\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lalalili\EmailCampaign\Enums\EmailEventType;

/**
 * @property int $id
 * @property int $delivery_id
 * @property EmailEventType $type
 * @property string|null $url
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed>|null $payload_json
 * @property-read EmailDelivery $delivery
 */
class EmailEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id',
        'type',
        'url',
        'occurred_at',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'type'         => EmailEventType::class,
            'occurred_at'  => 'immutable_datetime',
            'payload_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EmailDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'delivery_id');
    }
}
