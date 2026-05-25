<?php

namespace Lalalili\EmailCampaign\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lalalili\EmailCampaign\Database\Factories\EmailCampaignFactory;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;

/**
 * @property int $id
 * @property int|null $marketing_activity_id
 * @property string $name
 * @property string|null $description
 * @property int|null $smtp_profile_id
 * @property int|null $audience_list_id
 * @property string|null $audience_email_column
 * @property int|null $survey_id
 * @property CarbonImmutable|null $audience_snapshot_at
 * @property int|null $audience_skipped_count
 * @property string $subject_template
 * @property string|null $html_template
 * @property string|null $text_template
 * @property EmailCampaignStatus $status
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $sent_at
 * @property array<string, mixed>|null $extras_json
 * @property int|null $created_by
 * @property-read EmailSmtpProfile|null $smtpProfile
 * @property-read Collection<int, EmailCampaignRecipient> $recipients
 * @property-read Collection<int, EmailDelivery> $deliveries
 */
class EmailCampaign extends Model
{
    /** @use HasFactory<EmailCampaignFactory> */
    use HasFactory;

    protected static function newFactory(): EmailCampaignFactory
    {
        return EmailCampaignFactory::new();
    }

    protected $fillable = [
        'marketing_activity_id',
        'name',
        'description',
        'smtp_profile_id',
        'audience_list_id',
        'audience_email_column',
        'survey_id',
        'audience_snapshot_at',
        'audience_skipped_count',
        'subject_template',
        'html_template',
        'text_template',
        'status',
        'scheduled_at',
        'sent_at',
        'extras_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => EmailCampaignStatus::class,
            'scheduled_at'           => 'datetime',
            'sent_at'                => 'datetime',
            'survey_id'              => 'integer',
            'audience_snapshot_at'   => 'datetime',
            'audience_skipped_count' => 'integer',
            'extras_json'            => 'array',
        ];
    }

    /**
     * @return BelongsTo<EmailSmtpProfile, $this>
     */
    public function smtpProfile(): BelongsTo
    {
        return $this->belongsTo(EmailSmtpProfile::class);
    }

    /**
     * @return HasMany<EmailCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    /**
     * @return HasMany<EmailDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }
}
