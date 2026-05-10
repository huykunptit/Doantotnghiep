<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->string('status', 50)->default('planned'); // planned/open/closed/cancelled
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'term_id', 'code']);
            $table->index(['term_id', 'cohort_id']);
            $table->index(['lecturer_id', 'term_id']);
            $table->index(['status', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sections');
    }
};
