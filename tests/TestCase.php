<?php

namespace Lalalili\EmailCampaign\Tests;

use Lalalili\EmailCampaign\EmailCampaignServiceProvider;
use Lalalili\PackageTestingSupport\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [EmailCampaignServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
