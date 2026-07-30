<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('offline_sessions', 'qr_enabled')) {
                $table->boolean('qr_enabled')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('offline_sessions', 'qr_mode')) {
                // manual | rotating | static
                $table->string('qr_mode', 20)->default('manual')->after('qr_enabled');
            }
            if (! Schema::hasColumn('offline_sessions', 'qr_rotate_seconds')) {
                $table->unsignedInteger('qr_rotate_seconds')->default(60)->after('qr_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            foreach (['qr_rotate_seconds', 'qr_mode', 'qr_enabled'] as $col) {
                if (Schema::hasColumn('offline_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
