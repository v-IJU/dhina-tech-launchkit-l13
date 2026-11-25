<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-language website: pages and posts get translation rows,
 * menu items get translated labels.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'posts'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->string('locale', 10)->nullable()->after('id');
                    $t->foreignId('translation_of')->nullable()->after('locale')->constrained($table)->cascadeOnDelete();
                    $t->unique(['translation_of', 'locale']);
                });
            }
        }

        Schema::table('menu_items', function (Blueprint $t) {
            $t->json('translations')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        foreach (['pages', 'posts'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropUnique(['translation_of', 'locale']);
                    $t->dropConstrainedForeignId('translation_of');
                    $t->dropColumn('locale');
                });
            }
        }

        Schema::table('menu_items', function (Blueprint $t) {
            $t->dropColumn('translations');
        });
    }
};
