<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI request: the in-panel assistant (tokens on your provider bill)
 * and every MCP tool call (tokens on the client's own AI plan, so 0 here).
 * SaaS mode: lives in each tenant's database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);                 // assistant | mcp
            $table->string('provider', 40)->nullable();    // groq, anthropic… (assistant)
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->json('tools')->nullable();             // tool names used
            $table->string('status', 20)->default('ok');   // ok | error | denied | limit
            $table->string('message', 500)->nullable();    // error text, or the start of the question
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
