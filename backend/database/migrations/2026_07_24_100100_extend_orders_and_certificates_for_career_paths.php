<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('career_path_id')
                ->nullable()
                ->after('course_id')
                ->constrained('career_paths')
                ->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE orders MODIFY course_id BIGINT UNSIGNED NULL');
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });

        Schema::table('user_certificates', function (Blueprint $table) {
            $table->foreignId('career_path_id')
                ->nullable()
                ->after('course_id')
                ->constrained('career_paths')
                ->nullOnDelete();
        });

        Schema::table('user_certificates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE user_certificates MODIFY course_id BIGINT UNSIGNED NULL');
        Schema::table('user_certificates', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_certificates', function (Blueprint $table) {
            $table->dropForeign(['career_path_id']);
            $table->dropColumn('career_path_id');
        });

        Schema::table('user_certificates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE user_certificates MODIFY course_id BIGINT UNSIGNED NOT NULL');
        Schema::table('user_certificates', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['career_path_id']);
            $table->dropColumn('career_path_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });
        DB::statement('ALTER TABLE orders MODIFY course_id BIGINT UNSIGNED NOT NULL');
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }
};
