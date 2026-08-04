<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason', 64); // unsubscribe, bounce, complaint
            $table->foreignId('source_delivery_id')
                ->nullable()
                ->constrained('email_deliveries')
                ->nullOnDelete();
            $table->timestamp('suppressed_at');
            $table->timestamps();

            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
