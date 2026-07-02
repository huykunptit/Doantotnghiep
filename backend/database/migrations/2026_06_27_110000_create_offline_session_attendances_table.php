<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offline_session_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('present'); // present, late, absent
            $table->dateTime('checked_in_at')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();

            // Prevent duplicate check-in
            $table->unique(['user_id', 'offline_session_id'], 'user_session_unique_attendance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_session_attendances');
    }
};
