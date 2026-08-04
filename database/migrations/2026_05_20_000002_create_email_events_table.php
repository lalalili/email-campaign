<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')
                ->constrained('email_deliveries')
                ->cascadeOnDelete();
            $table->string('type', 32); // open, click, bounce, complaint, unsubscribe
            $table->string('url', 2048)->nullable(); // for click events
            $table->timestamp('occurred_at');
            $table->json('payload_json')->nullable();

            $table->index(['delivery_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
