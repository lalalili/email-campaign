<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_smtp_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mailer')->default('smtp');
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('encryption')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_smtp_profiles');
    }
};
