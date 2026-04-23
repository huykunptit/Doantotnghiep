<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('chatgpt');           // chatgpt | gemini | claude
            $table->string('model')->default('gpt-4o-mini');          // model name
            $table->string('api_key')->nullable();                    // encrypted API key
            $table->integer('monthly_token_quota')->default(1000000); // total token budget per month
            $table->integer('tokens_used')->default(0);               // tokens consumed this period
            $table->integer('max_requests_per_minute')->default(60);  // rate limit
            $table->boolean('is_active')->default(true);              // kill switch
            $table->timestamp('quota_reset_at')->nullable();          // when to reset token counter
            $table->timestamps();
        });

        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('endpoint');                               // /chat, /parse-cv, /recommend
            $table->string('provider');                               // chatgpt | gemini | claude
            $table->string('model');                                  // gpt-4o-mini, gemini-2.0-flash, etc.
            $table->integer('tokens_used')->default(0);
            $table->integer('response_time_ms')->default(0);         // latency in ms
            $table->string('status')->default('success');             // success | error
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_settings');
    }
};
