<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_sessions', 'course_id')) {
                $table->foreignId('course_id')
                    ->nullable()
                    ->after('class_section_id')
                    ->constrained('courses')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('offline_sessions', 'administrative_class_id')) {
                $table->foreignId('administrative_class_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('administrative_classes')
                    ->nullOnDelete();
            }
            $table->index(['course_id', 'administrative_class_id'], 'offline_sessions_course_admin_class_idx');
        });
    }

    public function down(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('offline_sessions', 'administrative_class_id')) {
                $table->dropIndex('offline_sessions_course_admin_class_idx');
                $table->dropConstrainedForeignId('administrative_class_id');
            }
            if (Schema::hasColumn('offline_sessions', 'course_id')) {
                $table->dropConstrainedForeignId('course_id');
            }
        });
    }
};
