<?php

use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaign\Support\MailerFactory;

function makeSmtpProfile(array $attributes = []): EmailSmtpProfile
{
    return EmailSmtpProfile::create([
        'name' => 'Test Profile',
        'mailer' => 'array',
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'user',
        'password' => 'secret',
        'from_address' => 'campaign@example.com',
        'from_name' => 'Campaign Sender',
        ...$attributes,
    ]);
}

it('does not mutate global mail config when building a profile mailer', function () {
    config(['mail.from.address' => 'global@example.com', 'mail.from.name' => 'Global']);
    $mailersBefore = config('mail.mailers');

    $profile = makeSmtpProfile();
    app(MailerFactory::class)->forProfile($profile);

    expect(config('mail.from.address'))->toBe('global@example.com')
        ->and(config('mail.from.name'))->toBe('Global')
        ->and(config('mail.mailers'))->toBe($mailersBefore)
        ->and(config("mail.mailers.email_campaign_profile_{$profile->id}"))->toBeNull();
});

it('sends with the profile from address without touching the default mailer', function () {
    config(['mail.from.address' => 'global@example.com', 'mail.from.name' => 'Global']);

    $profile = makeSmtpProfile();
    $mailer = app(MailerFactory::class)->forProfile($profile);

    expect($mailer)->toBeInstanceOf(Mailer::class);

    $mailer->raw('hello', fn ($message) => $message->to('someone@example.com'));

    $transport = $mailer->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);

    $sent = $transport->messages()->first()->getOriginalMessage();
    expect($sent->getFrom()[0]->getAddress())->toBe('campaign@example.com')
        ->and($sent->getFrom()[0]->getName())->toBe('Campaign Sender');
});

it('reuses the built mailer for the same profile and rebuilds after profile update', function () {
    $profile = makeSmtpProfile();
    $factory = app(MailerFactory::class);

    $first = $factory->forProfile($profile);
    $second = $factory->forProfile($profile);
    expect($second)->toBe($first);

    $profile->forceFill(['updated_at' => now()->addMinute()])->save();
    $rebuilt = $factory->forProfile($profile->fresh());
    expect($rebuilt)->not->toBe($first);
});

it('falls back to the default mailer when no profile is given', function () {
    $factory = app(MailerFactory::class);

    expect($factory->forProfile(null))->toBe(app('mail.manager')->mailer());
});

it('is registered as a singleton so the mailer cache survives across resolves', function () {
    expect(app(MailerFactory::class))->toBe(app(MailerFactory::class));
});
