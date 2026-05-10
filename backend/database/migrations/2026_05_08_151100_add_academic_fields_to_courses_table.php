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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('course_mode')->default('extension')->after('price');
            $table->foreignId('program_type_id')->nullable()->after('category_id')->constrained('program_types')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('program_type_id')->constrained('programs')->nullOnDelete();
            $table->foreignId('major_id')->nullable()->after('program_id')->constrained('majors')->nullOnDelete();
            $table->foreignId('curriculum_id')->nullable()->after('major_id')->constrained('curricula')->nullOnDelete();
            $table->boolean('is_credit_bearing')->default(false)->after('course_mode');
            $table->unsignedSmallInteger('credit_value')->nullable()->after('is_credit_bearing');

            $table->index(['course_mode', 'status']);
            $table->index(['program_id', 'major_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['course_mode', 'status']);
            $table->dropIndex(['program_id', 'major_id']);
            $table->dropConstrainedForeignId('curriculum_id');
            $table->dropConstrainedForeignId('major_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('program_type_id');
            $table->dropColumn(['course_mode', 'is_credit_bearing', 'credit_value']);
        });
    }
};
