<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in error tracker (Admin > System > Error log). One row per distinct error
 * (same exception class + first file:line in your code); repeats only increase "occurrences".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->mediumText('trace')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('user_type', 20)->nullable(); // staff | customer | api | console
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
