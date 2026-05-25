<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->string('tracking_token', 64)->nullable()->unique()->after('rendered_subject');
            $table->timestamp('opened_at')->nullable()->after('tracking_token');
        });
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropUnique('email_deliveries_tracking_token_unique');
            $table->dropColumn(['tracking_token', 'opened_at']);
        });
    }
};
