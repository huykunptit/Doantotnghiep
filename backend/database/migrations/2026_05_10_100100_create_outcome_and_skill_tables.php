<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('code', 32);
            $table->text('description');
            $table->string('level', 20)->default('skill'); // knowledge|skill|attitude
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['program_id', 'code']);
        });

        Schema::create('course_learning_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('code', 32);
            $table->text('description');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'code']);
        });

        Schema::create('clo_plo_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clo_id')->constrained('course_learning_outcomes')->cascadeOnDelete();
            $table->foreignId('plo_id')->constrained('program_learning_outcomes')->cascadeOnDelete();
            $table->decimal('weight', 4, 2)->default(1.0);
            $table->timestamps();

            $table->unique(['clo_id', 'plo_id']);
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->string('category', 64)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('course_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->decimal('weight', 4, 2)->default(1.0);
            $table->timestamps();

            $table->unique(['course_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('clo_plo_map');
        Schema::dropIfExists('course_learning_outcomes');
        Schema::dropIfExists('program_learning_outcomes');
    }
};
