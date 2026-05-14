<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number'); // 1..N (kỳ học trong CTĐT)
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('credits')->nullable();
            $table->unsignedSmallInteger('position')->default(0); // thứ tự hiển thị trong 1 kỳ
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_id', 'course_id']);
            $table->index(['curriculum_id', 'term_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
    }
};
