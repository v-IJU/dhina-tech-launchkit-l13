<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per uploaded import file: status, progress and counts (module import pages show these).
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 60)->index();                 // "faqs", "users"… = the permission prefix
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('path');                                 // on the private "local" disk
            $table->string('status', 20)->default('pending');       // pending, running, done, failed
            $table->boolean('queued')->default(false);              // big file → background jobs
            $table->boolean('dry_run')->default(false);             // "only check", nothing saved
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('message')->nullable();                    // why the whole import failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Rows that were skipped, with the reason. Downloadable as a spreadsheet to fix and import again.
        Schema::create('import_run_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
            $table->unsignedInteger('row');
            $table->json('messages');
            $table->json('values');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_run_errors');
        Schema::dropIfExists('import_runs');
    }
};
