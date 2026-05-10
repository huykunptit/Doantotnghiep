<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('advisor_id')
                ->nullable()
                ->after('cohort_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('advisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['advisor_id']);
            $table->dropConstrainedForeignId('advisor_id');
        });
    }
};
