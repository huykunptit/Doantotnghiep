<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            $table->foreignId('administrative_class_id')
                ->nullable()
                ->after('cohort_id')
                ->constrained('administrative_classes')
                ->nullOnDelete();

            $table->index('administrative_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            $table->dropIndex(['administrative_class_id']);
            $table->dropConstrainedForeignId('administrative_class_id');
        });
    }
};
