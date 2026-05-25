<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('audience_list_row_id')->nullable()->after('email_campaign_id');
            $table->index('audience_list_row_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['audience_list_row_id']);
            $table->dropColumn('audience_list_row_id');
        });
    }
};
