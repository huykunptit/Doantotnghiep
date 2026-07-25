<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thời khóa biểu theo LỚP HÀNH CHÍNH (BA 2026): cả lớp cùng lịch.
 * Mỗi dòng = 1 buổi học cố định trong tuần cho 1 môn của lớp trong 1 kỳ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrative_class_id')->constrained('administrative_classes')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = Thứ 2 ... 7 = Chủ nhật
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->timestamps();

            $table->index(['administrative_class_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
