<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            $table->foreignId('class_section_id')->nullable()->constrained('class_sections')->nullOnDelete()->after('lesson_id');
            $table->string('title')->nullable()->after('class_section_id');
            $table->string('qr_token', 64)->nullable()->unique()->after('location');
            $table->dateTime('qr_expires_at')->nullable()->after('qr_token');
            $table->decimal('latitude', 10, 7)->nullable()->after('qr_expires_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_active')->default(false)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('offline_sessions', function (Blueprint $table) {
            $table->dropForeign(['class_section_id']);
            $table->dropColumn(['class_section_id', 'title', 'qr_token', 'qr_expires_at', 'latitude', 'longitude', 'is_active']);
        });
    }
};
