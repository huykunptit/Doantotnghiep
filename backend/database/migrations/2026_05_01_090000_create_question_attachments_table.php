<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_attachments')) {
            Schema::create('question_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('file_size')->nullable();
                $table->string('mime_type')->nullable();
                $table->string('type')->default('file'); // file, image, audio
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_attachments');
    }
};
