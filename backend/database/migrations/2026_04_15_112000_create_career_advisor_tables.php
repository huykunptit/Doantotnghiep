<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->text('parsed_text')->nullable();
            $table->json('skills')->nullable();
            $table->timestamps();
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company')->nullable();
            $table->text('description');
            $table->json('required_skills');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('career_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_id')->nullable()->constrained('job_postings')->onDelete('set null');
            $table->integer('match_score')->default(0);
            $table->json('skill_gaps')->nullable();
            $table->json('suggested_courses')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_recommendations');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('user_cvs');
    }
};
