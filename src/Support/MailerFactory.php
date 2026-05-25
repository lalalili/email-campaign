<?php

namespace Lalalili\EmailCampaign\Support;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;

class MailerFactory
{
    public function __construct(private MailFactory $manager)
    {
    }

    public function forProfile(?EmailSmtpProfile $profile): Mailer
    {
        if ($profile === null) {
            return $this->manager->mailer();
        }

        $key = 'email_campaign_profile_'.$profile->id;

        config([
            "mail.mailers.{$key}" => [
                'transport'  => $profile->mailer,
                'host'       => $profile->host,
                'port'       => $profile->port,
                'encryption' => $profile->encryption,
                'username'   => $profile->username,
                'password'   => $profile->password,
            ],
            'mail.from.address' => $profile->from_address,
            'mail.from.name'    => $profile->from_name,
        ]);

        return $this->manager->mailer($key);
    }
}
