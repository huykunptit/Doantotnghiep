<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_qas', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('course_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->string('subject');
            $blueprint->text('content');
            $blueprint->timestamps();
        });

        Schema::create('course_qa_replies', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('course_qa_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
            $blueprint->text('content');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_qa_replies');
        Schema::dropIfExists('course_qas');
    }
};
