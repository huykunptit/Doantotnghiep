<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_groups')) {
            Schema::create('question_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_bank_id')->nullable()->constrained('question_banks')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('questions') && !Schema::hasColumn('questions', 'question_group_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->foreignId('question_group_id')->nullable()->after('question_bank_id')->constrained('question_groups')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('exams')) {
            Schema::create('exams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('draft');
                $table->integer('duration')->nullable();
                $table->integer('pass_score')->default(80);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('quizzes')) {
            if (!Schema::hasColumn('quizzes', 'course_id')) {
                Schema::table('quizzes', function (Blueprint $table) {
                    $table->foreignId('course_id')->nullable()->after('lesson_id')->constrained()->nullOnDelete();
                });
            }

            if (!Schema::hasColumn('quizzes', 'exam_id')) {
                Schema::table('quizzes', function (Blueprint $table) {
                    $table->foreignId('exam_id')->nullable()->after('course_id')->constrained('exams')->nullOnDelete();
                });
            }

            if (!Schema::hasColumn('quizzes', 'scope')) {
                Schema::table('quizzes', function (Blueprint $table) {
                    $table->string('scope')->default('lesson')->after('exam_id');
                });
            }

            DB::table('quizzes')
                ->leftJoin('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
                ->whereNull('quizzes.course_id')
                ->update([
                    'quizzes.course_id' => DB::raw('lessons.course_id'),
                    'quizzes.scope' => 'lesson',
                ]);

            $driver = Schema::getConnection()->getDriverName();
            if (Schema::hasColumn('quizzes', 'lesson_id')) {
                try {
                    if ($driver === 'mysql') {
                        DB::statement('ALTER TABLE quizzes MODIFY lesson_id BIGINT UNSIGNED NULL');
                    }
                } catch (\Throwable $e) {
                    // Keep migration resilient on environments where the column is already nullable
                    // or where the database engine does not allow this statement.
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quizzes')) {
            Schema::table('quizzes', function (Blueprint $table) {
                if (Schema::hasColumn('quizzes', 'scope')) {
                    $table->dropColumn('scope');
                }
                if (Schema::hasColumn('quizzes', 'exam_id')) {
                    $table->dropConstrainedForeignId('exam_id');
                }
                if (Schema::hasColumn('quizzes', 'course_id')) {
                    $table->dropConstrainedForeignId('course_id');
                }
            });
        }

        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'question_group_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('question_group_id');
            });
        }

        Schema::dropIfExists('exams');
        Schema::dropIfExists('question_groups');
    }
};
