<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->unsignedBigInteger('marketing_activity_id')->nullable()->after('id')
                ->comment('由行銷活動派送產生的 campaign 反向連結');
            $table->index('marketing_activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->dropIndex(['marketing_activity_id']);
            $table->dropColumn('marketing_activity_id');
        });
    }
};
