<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which kind of account a sign-in event belongs to:
 * "web" = staff (admin panel / staff API), "customer" = website / shop API customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_events', function (Blueprint $table) {
            $table->string('guard', 20)->default('web')->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('login_events', function (Blueprint $table) {
            $table->dropIndex(['guard']);
            $table->dropColumn('guard');
        });
    }
};
