<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('audience_list_id')->nullable()->after('smtp_profile_id');
            $table->string('audience_email_column')->nullable()->after('audience_list_id');
            $table->timestamp('audience_snapshot_at')->nullable()->after('audience_email_column');
            $table->unsignedInteger('audience_skipped_count')->default(0)->after('audience_snapshot_at');

            $table->index('audience_list_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropIndex(['audience_list_id']);
            $table->dropColumn([
                'audience_list_id',
                'audience_email_column',
                'audience_snapshot_at',
                'audience_skipped_count',
            ]);
        });
    }
};
