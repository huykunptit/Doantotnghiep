<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Upgrade questions table ──────────────────────────────────────
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('questions', 'default_score')) {
                $table->decimal('default_score', 8, 2)->default(1.00)->after('difficulty');
            }
            if (!Schema::hasColumn('questions', 'feedback')) {
                $table->text('feedback')->nullable()->after('explanation');
            }
            if (!Schema::hasColumn('questions', 'general_feedback')) {
                $table->text('general_feedback')->nullable()->after('feedback');
            }
            if (!Schema::hasColumn('questions', 'metadata')) {
                $table->json('metadata')->nullable()->after('general_feedback');
            }
        });

        // ── 2. Upgrade exams table ──────────────────────────────────────────
        // Make course_id nullable for standalone exams
        if (Schema::hasColumn('exams', 'course_id')) {
            try {
                $driver = Schema::getConnection()->getDriverName();
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE exams MODIFY course_id BIGINT UNSIGNED NULL');
                }
                // Remove the existing foreign key constraint if present
                // Re-add as nullable
            } catch (\Throwable $e) {
                // Column may already be nullable
            }
        }

        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'type')) {
                $table->string('type')->default('course_final')->after('description');
                // course_final | standalone
            }
            if (!Schema::hasColumn('exams', 'max_attempts')) {
                $table->unsignedInteger('max_attempts')->default(1)->after('pass_score');
            }
            if (!Schema::hasColumn('exams', 'shuffle_questions')) {
                $table->boolean('shuffle_questions')->default(false)->after('max_attempts');
            }
            if (!Schema::hasColumn('exams', 'shuffle_answers')) {
                $table->boolean('shuffle_answers')->default(false)->after('shuffle_questions');
            }
            if (!Schema::hasColumn('exams', 'review_options')) {
                $table->json('review_options')->nullable()->after('shuffle_answers');
            }
            if (!Schema::hasColumn('exams', 'proctoring_enabled')) {
                $table->boolean('proctoring_enabled')->default(false)->after('review_options');
            }
            if (!Schema::hasColumn('exams', 'proctoring_settings')) {
                $table->json('proctoring_settings')->nullable()->after('proctoring_enabled');
            }
            if (!Schema::hasColumn('exams', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('proctoring_settings')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // ── 3. Upgrade quiz_attempts table ──────────────────────────────────
        Schema::table('quiz_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_attempts', 'status')) {
                $table->string('status')->default('in_progress')->after('quiz_id');
                // in_progress | paused | submitted | force_stopped
            }
            if (!Schema::hasColumn('quiz_attempts', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('quiz_attempts', 'resumed_at')) {
                $table->timestamp('resumed_at')->nullable()->after('paused_at');
            }
            if (!Schema::hasColumn('quiz_attempts', 'paused_duration')) {
                $table->unsignedInteger('paused_duration')->default(0)->after('resumed_at');
                // Total seconds paused
            }
            if (!Schema::hasColumn('quiz_attempts', 'time_extensions')) {
                $table->unsignedInteger('time_extensions')->default(0)->after('paused_duration');
                // Total seconds extended by admin
            }
            if (!Schema::hasColumn('quiz_attempts', 'auto_saved_at')) {
                $table->timestamp('auto_saved_at')->nullable()->after('time_extensions');
            }
            if (!Schema::hasColumn('quiz_attempts', 'force_stop_reason')) {
                $table->text('force_stop_reason')->nullable()->after('auto_saved_at');
            }
            if (!Schema::hasColumn('quiz_attempts', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('force_stop_reason');
            }
            if (!Schema::hasColumn('quiz_attempts', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });

        // ── 4. Create exam_enrollments table (for standalone exams) ─────────
        if (!Schema::hasTable('exam_enrollments')) {
            Schema::create('exam_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('enrolled_at')->useCurrent();
                $table->timestamps();

                $table->unique(['exam_id', 'user_id']);
            });
        }

        // ── 5. Create exam_violations table (for proctoring logs) ───────────
        if (!Schema::hasTable('exam_violations')) {
            Schema::create('exam_violations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type'); // focus_lost, no_face, multiple_faces, suspicious
                $table->string('severity')->default('warning'); // warning | critical
                $table->string('snapshot_url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_violations');
        Schema::dropIfExists('exam_enrollments');

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $cols = ['status', 'paused_at', 'resumed_at', 'paused_duration', 'time_extensions',
                     'auto_saved_at', 'force_stop_reason', 'ip_address', 'user_agent'];
            $drop = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('quiz_attempts', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) $table->dropColumn($drop);
        });

        Schema::table('exams', function (Blueprint $table) {
            $cols = ['type', 'max_attempts', 'shuffle_questions', 'shuffle_answers',
                     'review_options', 'proctoring_enabled', 'proctoring_settings'];
            $drop = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('exams', $col)) {
                    $drop[] = $col;
                }
            }
            if (Schema::hasColumn('exams', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if ($drop) $table->dropColumn($drop);
        });

        Schema::table('questions', function (Blueprint $table) {
            $cols = ['code', 'default_score', 'feedback', 'general_feedback', 'metadata'];
            $drop = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('questions', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) $table->dropColumn($drop);
        });
    }
};
