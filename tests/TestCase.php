<?php

namespace Lalalili\EmailCampaign\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lalalili\EmailCampaign\EmailCampaignServiceProvider;
use Lalalili\PackageTestingSupport\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [EmailCampaignServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // SendCampaignEmailJob 以 Redis 計數器收斂完成檢查；Testbench 預設 127.0.0.1，改吃 Sail 可解析的 Redis 主機。
        config()->set('database.redis.default.host', $this->redisHost());
        config()->set('database.redis.options.prefix', 'email-campaign-test:');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../audience-core/database/migrations');

        // AudienceList 使用 spatie LogsActivity，測試環境補最小 activity_log 表。
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    private function redisHost(): string
    {
        $host = env('REDIS_HOST');

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return gethostbyname('valkey') !== 'valkey' ? 'valkey' : 'redis';
    }
}
