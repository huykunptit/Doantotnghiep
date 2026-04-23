<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add 'type' to lessons table
        Schema::table('lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('lessons', 'type')) {
                $table->string('type')->default('video')->after('title');
            }
        });

        // 2. Drop old tables explicitly if needed (we rely on rolling back if we messed up, but since data is new, we will just alter them or drop and recreate)
        // Since quiz_attempts depends on quizzes, and answers depend on questions, let's just drop them all and recreate for Question Bank
        
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_questions');

        // 3. Create new `questions` table for the Question Bank
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('type')->default('single_choice');
            $table->timestamps();
        });

        // 4. Create new `answers` table mapping to `questions`
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        // 5. Create `quiz_question` pivot table
        Schema::create('quiz_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->integer('points')->default(10);
            $table->timestamps();
            
            $table->unique(['quiz_id', 'question_id']);
        });

        // 6. Recreate `quiz_attempts` pointing directly to quiz_id and user_id, answers are stored as JSON
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->json('answers_data')->nullable(); // stored as {question_id: answer_id(s)}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_question');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('questions');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
