<?php

namespace Lalalili\EmailCampaign\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lalalili\AudienceCore\Concerns\LogsModelActivity;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;

/**
 * @property int $id
 * @property string $name
 * @property string $mailer
 * @property string $host
 * @property int $port
 * @property string|null $encryption
 * @property string|null $username
 * @property string|null $password
 * @property string $from_address
 * @property string|null $from_name
 * @property bool $is_default
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EmailCampaign> $campaigns
 */
class EmailSmtpProfile extends Model
{
    use LogsModelActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $profile): void {
            if (! $profile->isForceDeleting() && ! $profile->canBeDeleted()) {
                throw new \DomainException($profile->deletionBlockReason());
            }
        });
    }

    /** @var list<string> */
    protected $hidden = ['password'];

    protected $fillable = [
        'name',
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_default' => 'boolean',
            'password' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<EmailCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class, 'smtp_profile_id');
    }

    public function canBeDeleted(): bool
    {
        return ! $this->campaigns()
            ->whereIn('status', [
                EmailCampaignStatus::Scheduled,
                EmailCampaignStatus::Sending,
            ])
            ->exists();
    }

    public function deletionBlockReason(): string
    {
        return '仍有排程中或寄送中的 Email 活動使用此 SMTP 設定檔，請先取消排程或等待寄送結束。';
    }

    /** @return list<string> */
    protected function activityLogExcept(): array
    {
        return ['password'];
    }
}
