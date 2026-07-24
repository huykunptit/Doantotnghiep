<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'learning_outcomes')) {
                $table->json('learning_outcomes')->nullable()->after('description');
            }
            if (!Schema::hasColumn('courses', 'benefits')) {
                $table->json('benefits')->nullable()->after('learning_outcomes');
            }
            if (!Schema::hasColumn('courses', 'requirements')) {
                $table->json('requirements')->nullable()->after('benefits');
            }
            if (!Schema::hasColumn('courses', 'level')) {
                $table->string('level', 32)->nullable()->after('requirements');
            }
            if (!Schema::hasColumn('courses', 'trailer_url')) {
                $table->string('trailer_url', 2048)->nullable()->after('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            foreach (['learning_outcomes', 'benefits', 'requirements', 'level', 'trailer_url'] as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
