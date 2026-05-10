<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('weight', 5, 2)->default(0);     // percentage of final grade
            $table->decimal('max_score', 6, 2)->default(10); // max raw score per entry
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'name']);
            $table->index(['course_id', 'position']);
        });

        Schema::create('grade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('grade_component_id')->constrained('grade_components')->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'grade_component_id']);
            $table->index(['grade_component_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_entries');
        Schema::dropIfExists('grade_components');
    }
};
