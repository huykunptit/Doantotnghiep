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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('bio')->constrained('institutions')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('institution_id')->constrained('units')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('unit_id')->constrained('programs')->nullOnDelete();
            $table->foreignId('major_id')->nullable()->after('program_id')->constrained('majors')->nullOnDelete();
            $table->foreignId('specialization_id')->nullable()->after('major_id')->constrained('specializations')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->after('specialization_id')->constrained('cohorts')->nullOnDelete();
            $table->string('user_type')->default('student')->after('cohort_id');
            $table->string('student_code')->nullable()->after('user_type');
            $table->string('staff_code')->nullable()->after('student_code');
            $table->string('phone')->nullable()->after('staff_code');

            $table->index(['institution_id', 'unit_id']);
            $table->index(['program_id', 'major_id', 'cohort_id']);
            $table->unique('student_code');
            $table->unique('staff_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'unit_id']);
            $table->dropIndex(['program_id', 'major_id', 'cohort_id']);
            $table->dropUnique(['student_code']);
            $table->dropUnique(['staff_code']);
            $table->dropConstrainedForeignId('cohort_id');
            $table->dropConstrainedForeignId('specialization_id');
            $table->dropConstrainedForeignId('major_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn(['user_type', 'student_code', 'staff_code', 'phone']);
        });
    }
};
