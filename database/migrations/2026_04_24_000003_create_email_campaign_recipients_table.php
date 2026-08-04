<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('user_name')->nullable();
            $table->string('external_id')->nullable()->index();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
    }
};
