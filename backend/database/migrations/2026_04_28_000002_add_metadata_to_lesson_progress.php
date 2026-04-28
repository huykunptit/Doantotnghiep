<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            // Free-form metadata bag — used by SCORM tracking to persist
            // version-specific keys (cmi.location, suspend_data, status, etc.)
            $table->json('metadata')->nullable()->after('last_watched_at');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
