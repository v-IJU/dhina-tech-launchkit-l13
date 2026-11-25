<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website customers live in their own table (not "users"), so a customer can
 * never reach the admin panel and staff never show up in customer lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable()->index();
            $table->string('password');
            $table->string('status', 20)->default('active')->index(); // active | blocked
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->text('notes')->nullable(); // staff only, never shown to the customer
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable(); // Home, Office…
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('India');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Customer sign-ins share the login_events table (guard = "customer", customer_id set).
        Schema::table('login_events', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
