<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $constraint = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'offline_sessions'
               AND COLUMN_NAME = 'lesson_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        if ($constraint) {
            DB::statement("ALTER TABLE `offline_sessions` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }

        DB::statement('ALTER TABLE `offline_sessions` MODIFY `lesson_id` BIGINT UNSIGNED NULL');

        Schema::table('offline_sessions', function (Blueprint $table) {
            $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            $table->dropForeign(['lesson_id']);
        });

        DB::statement('ALTER TABLE `offline_sessions` MODIFY `lesson_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('offline_sessions', function (Blueprint $table) {
            $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
        });
    }
};
