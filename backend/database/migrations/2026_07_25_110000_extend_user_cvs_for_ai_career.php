<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BA 2026 — AI Career: form tạo CV + định hướng nghề/lương + đánh giá.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: cho phép CV không có file (tạo bằng form)
        DB::statement('ALTER TABLE user_cvs MODIFY file_path VARCHAR(255) NULL');
        DB::statement('ALTER TABLE user_cvs MODIFY file_name VARCHAR(255) NULL');

        Schema::table('user_cvs', function (Blueprint $table) {
            $table->string('source')->default('upload')->after('file_name');
            $table->json('profile_json')->nullable()->after('skills');
            $table->string('target_role')->nullable()->after('profile_json');
            $table->unsignedInteger('expected_salary')->nullable()->after('target_role');
            $table->json('evaluation_json')->nullable()->after('expected_salary');
        });
    }

    public function down(): void
    {
        Schema::table('user_cvs', function (Blueprint $table) {
            $table->dropColumn(['source', 'profile_json', 'target_role', 'expected_salary', 'evaluation_json']);
        });
    }
};
