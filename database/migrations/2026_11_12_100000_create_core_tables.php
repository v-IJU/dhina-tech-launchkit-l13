<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Admin sidebar (location=admin) and website menus (header / footer).
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20)->default('admin')->index();
            $table->string('key')->nullable()->index();
            $table->string('module')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('group')->nullable();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            $table->string('url')->nullable();
            $table->string('permission')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();
        });

        // Extra details for roles shown on the Roles page.
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('description')->nullable()->after('display_name');
            $table->string('color', 20)->default('#2563eb')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'color']);
        });
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('settings');
    }
};
