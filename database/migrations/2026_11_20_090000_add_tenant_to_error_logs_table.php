<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS mode: errors from every tenant are saved centrally, tagged with the tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('fingerprint')->index();
        });
    }

    public function down(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
