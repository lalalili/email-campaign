<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * finalizeIfComplete 每封信結算都會做 (email_campaign_id, status) 的 count，
     * 大型活動下需要複合索引支撐。
     */
    public function up(): void
    {
        if (Schema::hasIndex('email_deliveries', 'email_deliveries_campaign_status_index')) {
            return;
        }

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->index(['email_campaign_id', 'status'], 'email_deliveries_campaign_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropIndex('email_deliveries_campaign_status_index');
        });
    }
};
