<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exams', 'variant_count')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->unsignedTinyInteger('variant_count')->default(1)->after('shuffle_answers');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exams', 'variant_count')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('variant_count');
            });
        }
    }
};
