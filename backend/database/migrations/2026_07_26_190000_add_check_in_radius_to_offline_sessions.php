<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_sessions', 'check_in_radius_meters')) {
                $table->unsignedInteger('check_in_radius_meters')->default(15)->after('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('offline_sessions', 'check_in_radius_meters')) {
                $table->dropColumn('check_in_radius_meters');
            }
        });
    }
};
