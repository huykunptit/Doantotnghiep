<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_assignments', function (Blueprint $table) {
            // Moodle-inspired milestones:
            //   available_from      → "Ngày nhận bài" (assignment becomes visible)
            //   submission_open_at  → "Ngày bắt đầu nộp" (allow submissions from)
            //   due_at              → "Ngày đóng" (cut-off, no submissions after) — already exists
            $table->dateTime('available_from')->nullable()->after('allowed_extensions');
            $table->dateTime('submission_open_at')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_assignments', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'submission_open_at']);
        });
    }
};
