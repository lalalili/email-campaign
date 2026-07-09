<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * finalizeIfComplete 每封信結算都會做 (email_campaign_id, status) 的 count，
     * 大型活動下需要複合索引支撐。
     */
    public function up(): void
    {
        if ($this->hasIndex('email_deliveries', 'email_deliveries_campaign_status_index')) {
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

    /**
     * Schema::hasIndex() 在 sqlsrv 底層用 STRING_AGG 聚合欄位名稱，
     * SQL Server 2016（無 STRING_AGG，2017+ 才有）會直接噴錯；這裡只需確認索引是否存在。
     */
    private function hasIndex(string $table, string $index): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return Schema::hasIndex($table, $index);
        }

        return (bool) DB::selectOne(
            'SELECT CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)) THEN 1 ELSE 0 END AS idx_exists',
            [$index, $table],
        )->idx_exists;
    }
};
