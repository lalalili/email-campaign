<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            // Must drop FKs before dropping the unique index (MariaDB requirement)
            $table->dropForeign(['email_campaign_id']);
            $table->dropForeign(['email_campaign_recipient_id']);

            $table->dropUnique('email_deliveries_campaign_recipient_unique');

            $table->unsignedBigInteger('email_campaign_id')->nullable()->change();
            $table->unsignedBigInteger('email_campaign_recipient_id')->nullable()->change();

            $table->foreign('email_campaign_id')
                ->references('id')->on('email_campaigns')
                ->cascadeOnDelete();

            // 同 create migration：SQL Server 不允許 multiple cascade paths。
            if (DB::getDriverName() === 'sqlsrv') {
                $table->foreign('email_campaign_recipient_id')
                    ->references('id')->on('email_campaign_recipients')
                    ->noActionOnDelete();
            } else {
                $table->foreign('email_campaign_recipient_id')
                    ->references('id')->on('email_campaign_recipients')
                    ->cascadeOnDelete();
            }

            $table->index(['email_campaign_id', 'email_campaign_recipient_id'], 'email_deliveries_campaign_recipient_index');

            // For transactional emails where recipient record doesn't exist
            $table->string('to_email')->nullable()->after('email_campaign_recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropColumn('to_email');
            $table->dropIndex('email_deliveries_campaign_recipient_index');
            $table->dropForeign(['email_campaign_id']);
            $table->dropForeign(['email_campaign_recipient_id']);

            $table->unsignedBigInteger('email_campaign_id')->nullable(false)->change();
            $table->unsignedBigInteger('email_campaign_recipient_id')->nullable(false)->change();

            $table->foreign('email_campaign_id')
                ->references('id')->on('email_campaigns')
                ->cascadeOnDelete();

            if (DB::getDriverName() === 'sqlsrv') {
                $table->foreign('email_campaign_recipient_id')
                    ->references('id')->on('email_campaign_recipients')
                    ->noActionOnDelete();
            } else {
                $table->foreign('email_campaign_recipient_id')
                    ->references('id')->on('email_campaign_recipients')
                    ->cascadeOnDelete();
            }

            $table->unique(['email_campaign_id', 'email_campaign_recipient_id'], 'email_deliveries_campaign_recipient_unique');
        });
    }
};
