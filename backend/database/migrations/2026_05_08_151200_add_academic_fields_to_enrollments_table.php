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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('term_id')->nullable()->after('course_id')->constrained('terms')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->after('term_id')->constrained('cohorts')->nullOnDelete();
            $table->string('enrollment_source')->default('marketplace')->after('order_id');

            $table->index(['term_id', 'cohort_id']);
            $table->index(['enrollment_source', 'enrolled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['term_id', 'cohort_id']);
            $table->dropIndex(['enrollment_source', 'enrolled_at']);
            $table->dropConstrainedForeignId('cohort_id');
            $table->dropConstrainedForeignId('term_id');
            $table->dropColumn('enrollment_source');
        });
    }
};
