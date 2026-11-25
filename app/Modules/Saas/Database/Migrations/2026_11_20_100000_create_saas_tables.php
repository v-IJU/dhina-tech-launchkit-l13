<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central database only (SaaS mode). Tenants' own databases never get these tables
 * (config/tenancy.php → migration_parameters excludes the Saas module).
 * Money is stored in minor units: paise / cents (49900 = ₹499.00).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price_monthly')->default(0);
            $table->unsignedBigInteger('price_yearly')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->json('modules')->nullable();   // ["blog", "customers", …] (core modules are always included)
            $table->json('limits')->nullable();    // {"users": 5, "customers": 1000, "storage_mb": 1024}; missing = unlimited
            $table->json('features')->nullable();  // bullet points for the pricing page
            $table->string('razorpay_plan_monthly')->nullable();
            $table->string('razorpay_plan_yearly')->nullable();
            $table->string('stripe_price_monthly')->nullable();
            $table->string('stripe_price_yearly')->nullable();
            $table->boolean('is_public')->default(true);   // shown on the pricing page
            $table->boolean('is_active')->default(true);   // can be chosen at all
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // stancl/tenancy tables, plus the columns the console filters and sorts on.
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->index();              // owner / billing email
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('trial')->index(); // trial | active | past_due | suspended | cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();               // everything else (stancl virtual column)
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();        // "acme" (subdomain) or "admin.acmecorp.com"
            $table->string('tenant_id');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });

        // "Sign in as tenant admin": one-time tokens (stancl UserImpersonation feature).
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('auth_guard');
            $table->string('redirect_url');
            $table->timestamp('created_at');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 20);                  // razorpay | stripe | manual
            $table->string('interval', 10)->default('month'); // month | year
            $table->string('status', 20)->default('pending')->index(); // pending | active | past_due | cancelled | ended
            $table->string('gateway_id')->nullable()->index();
            $table->string('gateway_customer_id')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount');            // before tax
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('currency', 3);
            $table->string('status', 10)->default('open')->index(); // open | paid | void
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->json('billing')->nullable();             // name, email, GSTIN, address at the time of the invoice
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->date('date');
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('customers')->default(0);
            $table->unsignedInteger('storage_mb')->default(0);
            $table->unsignedInteger('logins_30d')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'date']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenant_user_impersonation_tokens');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
