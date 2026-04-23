<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Zoom/Google Meet/Virtual Class support
        Schema::create('virtual_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // zoom, google_meet, jitsi
            $table->string('meeting_id')->nullable();
            $table->string('meeting_password')->nullable();
            $table->string('join_url')->nullable();
            $table->string('start_url')->nullable(); // For instructor
            $table->dateTime('start_at');
            $table->integer('duration'); // in minutes
            $table->timestamps();
        });

        // 2. SCORM support
        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('uuid')->unique();
            $table->string('version')->default('1.2'); // 1.2, 2004
            $table->string('entry_url'); // index.html path in storage
            $table->string('identifier')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        // 3. Offline session support
        Schema::create('offline_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->dateTime('start_at');
            $table->integer('duration')->nullable();
            $table->integer('max_participants')->nullable();
            $table->timestamps();
        });

        // 4. Assignments support
        Schema::create('lesson_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('instructions');
            $table->integer('max_file_size')->default(10240); // 10MB in KB
            $table->string('allowed_extensions')->default('pdf,doc,docx,zip');
            $table->dateTime('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_url');
            $table->text('student_note')->nullable();
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('lesson_assignments');
        Schema::dropIfExists('offline_sessions');
        Schema::dropIfExists('scorm_packages');
        Schema::dropIfExists('virtual_classes');
    }
};
