<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('class_section_id')
                ->nullable()
                ->after('cohort_id')
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->index(['class_section_id', 'enrollment_source']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['class_section_id', 'enrollment_source']);
            $table->dropConstrainedForeignId('class_section_id');
        });
    }
};
