<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Question Banks table
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Update Questions table
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'question_bank_id')) {
                $table->foreignId('question_bank_id')->nullable()->after('course_id')->constrained('question_banks')->nullOnDelete();
            }
            if (!Schema::hasColumn('questions', 'difficulty')) {
                $table->integer('difficulty')->default(1)->after('type'); // 1-5
            }
            if (!Schema::hasColumn('questions', 'explanation')) {
                $table->text('explanation')->nullable()->after('difficulty');
            }
        });

        // 3. Update Answers table
        Schema::table('answers', function (Blueprint $table) {
            if (!Schema::hasColumn('answers', 'sub_content')) {
                $table->text('sub_content')->nullable()->after('content'); // For matching pairs
            }
            if (!Schema::hasColumn('answers', 'sort_order')) {
                $table->integer('sort_order')->nullable()->after('sub_content'); // For ordering questions
            }
            if (!Schema::hasColumn('answers', 'order')) {
                $table->integer('order')->default(0)->after('sort_order');
            }
        });

        // 4. Update Quizzes table for randomization
        Schema::table('quizzes', function (Blueprint $table) {
            if (!Schema::hasColumn('quizzes', 'settings')) {
                $table->json('settings')->nullable()->after('pass_score'); 
                // Settings might look like: {"randomize": true, "rules": [{"bank_id": 1, "count": 10}]}
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('settings');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn(['sub_content', 'sort_order', 'order']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['question_bank_id']);
            $table->dropColumn(['question_bank_id', 'difficulty', 'explanation']);
        });

        Schema::dropIfExists('question_banks');
    }
};
