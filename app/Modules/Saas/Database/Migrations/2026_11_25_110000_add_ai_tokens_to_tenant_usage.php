<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI assistant tokens used this month, measured nightly per tenant (central database). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_tokens')->default(0)->after('storage_mb');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_usage', fn (Blueprint $table) => $table->dropColumn('ai_tokens'));
    }
};
