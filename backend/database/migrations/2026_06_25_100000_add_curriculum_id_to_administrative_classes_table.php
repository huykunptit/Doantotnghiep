<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrative_classes', function (Blueprint $table) {
            $table->foreignId('curriculum_id')
                ->nullable()
                ->after('advisor_id')
                ->constrained('curricula')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('administrative_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curriculum_id');
        });
    }
};
