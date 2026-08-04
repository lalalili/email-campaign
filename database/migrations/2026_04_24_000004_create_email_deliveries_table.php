<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();

            // SQL Server 不允許 multiple cascade paths（campaigns→deliveries 與
            // campaigns→recipients→deliveries）：recipient FK 改 NO ACTION，
            // 直接刪 recipient 前由應用層先清 deliveries。
            if (DB::getDriverName() === 'sqlsrv') {
                $table->foreignId('email_campaign_recipient_id')->constrained()->noActionOnDelete();
            } else {
                $table->foreignId('email_campaign_recipient_id')->constrained()->cascadeOnDelete();
            }
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('rendered_subject')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email_campaign_recipient_id'], 'email_deliveries_campaign_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
