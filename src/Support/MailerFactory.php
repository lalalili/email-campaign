<?php

namespace Lalalili\EmailCampaign\Support;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;

class MailerFactory
{
    /**
     * 以 profile id + updated_at 為鍵快取已建立的 mailer，
     * 讓同一 worker 連續寄送可重用 SMTP transport，profile 更新後自動失效。
     *
     * @var array<string, Mailer>
     */
    private array $mailers = [];

    public function __construct(private MailFactory $manager) {}

    public function forProfile(?EmailSmtpProfile $profile): Mailer
    {
        // 非 MailManager（如測試的 MailFake）無 build()，直接回預設 mailer 讓 fake 攔截寄送。
        if ($profile === null || ! $this->manager instanceof MailManager) {
            return $this->manager->mailer();
        }

        $key = $profile->id.':'.($profile->updated_at?->getTimestamp() ?? 0);

        if (isset($this->mailers[$key])) {
            return $this->mailers[$key];
        }

        $mailer = $this->manager->build([
            'name' => 'email_campaign_profile_'.$profile->id,
            'transport' => $profile->mailer,
            'host' => $profile->host,
            'port' => $profile->port,
            'encryption' => $profile->encryption,
            'username' => $profile->username,
            'password' => $profile->password,
        ]);

        $mailer->alwaysFrom($profile->from_address, $profile->from_name);

        return $this->mailers[$key] = $mailer;
    }
}
