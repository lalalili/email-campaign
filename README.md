# Email Campaign

Standalone EDM and email campaign engine for Laravel applications.

`lalalili/email-campaign` owns campaign templates, recipients, SMTP profiles, scheduling, delivery tracking, suppression, and variable rendering. It integrates with `audience-core` and can react to survey invitation events.

## Features

- Campaign, recipient, delivery, event, SMTP profile, and suppression models.
- Scheduled campaign dispatch with queue-backed delivery jobs.
- Configurable variable providers and template rendering.
- Open, click, and unsubscribe tracking routes.
- Audience list synchronization.
- Survey invitation listener integration.
- Demo safe mode to record attempts without contacting a real mail transport.

## Installation

```bash
composer require lalalili/email-campaign
php artisan vendor:publish --tag=email-campaign-config
php artisan vendor:publish --tag=email-campaign-migrations
php artisan migrate
```

For GitHub installs before a Packagist release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lalalili/audience-core.git"},
        {"type": "vcs", "url": "https://github.com/lalalili/email-campaign.git"}
    ]
}
```

## Configuration

`config/email-campaign.php` controls:

- variable providers
- automatic scheduler registration
- demo safe mode
- queue connection and queue name

Important environment values:

```dotenv
EMAIL_CAMPAIGN_DEMO_SAFE_MODE=true
EMAIL_CAMPAIGN_QUEUE_CONNECTION=
EMAIL_CAMPAIGN_QUEUE=default
```

## Routes

The package registers tracking routes:

```text
GET /email/track/open/{token}
GET /email/track/click/{token}
GET /email/track/unsubscribe/{token}
```

## Usage

Send a due campaign through the scheduler action:

```php
use Lalalili\EmailCampaign\Actions\ScheduleDueCampaignsAction;

app(ScheduleDueCampaignsAction::class)();
```

Send a campaign directly:

```php
use Lalalili\EmailCampaign\Actions\SendCampaignAction;

app(SendCampaignAction::class)->execute($campaign);
```

## Boundaries

- This package owns campaign delivery domain logic, not admin UI.
- Filament resources live in `lalalili/email-campaign-filament`.
- Host applications own sender policy, queue workers, real SMTP credentials, and compliance copy.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
