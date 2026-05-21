<?php

namespace Lalalili\EmailCampaign\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $email
 * @property string $reason
 * @property int|null $source_delivery_id
 * @property CarbonImmutable $suppressed_at
 * @property-read EmailDelivery|null $sourceDelivery
 */
class EmailSuppression extends Model
{
    protected $fillable = [
        'email',
        'reason',
        'source_delivery_id',
        'suppressed_at',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<EmailDelivery, $this>
     */
    public function sourceDelivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'source_delivery_id');
    }

    public static function isSuppressed(string $email): bool
    {
        return self::where('email', mb_strtolower($email))->exists();
    }
}
