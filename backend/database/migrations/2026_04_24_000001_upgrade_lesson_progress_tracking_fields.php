<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('lesson_id');
            $table->unsignedInteger('last_position')->default(0)->after('progress_percent');
            $table->timestamp('completed_at')->nullable()->after('completed');
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropUnique('lesson_progress_user_id_lesson_id_unique');
            $table->dropColumn(['progress_percent', 'last_position', 'completed_at']);
        });
    }
};

