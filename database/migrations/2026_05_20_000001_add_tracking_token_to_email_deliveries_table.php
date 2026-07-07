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
            $table->string('tracking_token', 64)->nullable()->after('rendered_subject');
            $table->timestamp('opened_at')->nullable()->after('tracking_token');
        });

        // SQL Server 的 unique index 視多個 NULL 為重複，需用 filtered index 排除 NULL。
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX email_deliveries_tracking_token_unique ON email_deliveries (tracking_token) WHERE tracking_token IS NOT NULL');
        } else {
            Schema::table('email_deliveries', function (Blueprint $table): void {
                $table->unique('tracking_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropUnique('email_deliveries_tracking_token_unique');
            $table->dropColumn(['tracking_token', 'opened_at']);
        });
    }
};
