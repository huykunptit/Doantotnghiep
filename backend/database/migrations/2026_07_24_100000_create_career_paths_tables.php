<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_paths', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('target_role')->nullable()->index(); // e.g. fullstack_python, business_analyst
            $table->unsignedInteger('price')->default(0); // VND
            $table->string('status')->default('draft')->index(); // draft | published | archived
            $table->string('cover_url')->nullable();
            $table->foreignId('certificate_template_id')
                ->nullable()
                ->constrained('certificate_templates')
                ->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('career_path_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_path_id')->constrained('career_paths')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->string('milestone_label')->nullable();
            $table->timestamps();

            $table->unique(['career_path_id', 'course_id']);
            $table->index(['career_path_id', 'sort_order']);
        });

        Schema::create('user_career_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_path_id')->constrained('career_paths')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            // following = free follow, purchased = paid/free checkout, completed = path done
            $table->string('status')->default('following')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'career_path_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_career_paths');
        Schema::dropIfExists('career_path_courses');
        Schema::dropIfExists('career_paths');
    }
};
