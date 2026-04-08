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
        Schema::table('lessons', function (Blueprint $table) {
            // Thêm section_id (nullable để tương thích với data cũ)
            $table->foreignId('section_id')->nullable()->after('course_id')->constrained()->onDelete('cascade');
            
            // Thêm các fields cho video
            // $table->integer('duration')->default(0)->comment('Video duration in seconds');
            $table->string('video_size')->nullable()->comment('File size in MB');
            $table->enum('video_status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            
            // Index
            $table->index(['section_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn(['section_id', 'duration', 'video_size', 'video_status']);
        });
    }
};

