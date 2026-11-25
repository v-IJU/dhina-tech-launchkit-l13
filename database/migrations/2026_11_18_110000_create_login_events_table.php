<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('event', 20)->index(); // login, failed, logout, lockout
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('device_hash', 64)->nullable()->index();
            $table->boolean('is_new_device')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
