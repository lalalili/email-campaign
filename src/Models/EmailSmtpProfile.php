<?php

namespace Lalalili\EmailCampaign\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property-read Collection<int, EmailCampaign> $campaigns
 */
class EmailSmtpProfile extends Model
{
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
            'port'       => 'integer',
            'is_default' => 'boolean',
            'password'   => 'encrypted',
        ];
    }

    /**
     * @return HasMany<EmailCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class);
    }
}
