<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('offline_sessions', 'room')) {
            Schema::table('offline_sessions', function (Blueprint $table) {
                $table->string('room', 100)->nullable()->after('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('offline_sessions', 'room')) {
            Schema::table('offline_sessions', function (Blueprint $table) {
                $table->dropColumn('room');
            });
        }
    }
};
