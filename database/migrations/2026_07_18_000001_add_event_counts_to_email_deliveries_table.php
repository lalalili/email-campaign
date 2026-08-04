<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * 反正規化每封派送的開信／點擊次數，避免統計時對 email_events 做 JOIN + distinct。
     * 由 LogEmailEventAction 於記錄事件時累加；此處回填既有資料。
     */
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('email_deliveries', 'open_count')) {
                $table->unsignedInteger('open_count')->default(0)->after('opened_at');
            }

            if (! Schema::hasColumn('email_deliveries', 'click_count')) {
                $table->unsignedInteger('click_count')->default(0)->after('open_count');
            }
        });

        // 相關子查詢回填（sqlite 與 sqlsrv 皆相容）。空表時子查詢回 0，維持欄位預設。
        DB::statement(
            "UPDATE email_deliveries SET open_count = (SELECT COUNT(*) FROM email_events WHERE email_events.delivery_id = email_deliveries.id AND email_events.type = 'open')"
        );
        DB::statement(
            "UPDATE email_deliveries SET click_count = (SELECT COUNT(*) FROM email_events WHERE email_events.delivery_id = email_deliveries.id AND email_events.type = 'click')"
        );
    }

    public function down(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['open_count', 'click_count']);
        });
    }
};
