<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_session_attendances', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('checked_in_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('distance_meters', 8, 2)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('offline_session_attendances', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'distance_meters']);
        });
    }
};
