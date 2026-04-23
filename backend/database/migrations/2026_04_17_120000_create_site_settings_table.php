<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('site_settings')->insert([
            ['key' => 'site_name', 'value' => 'EduPress LMS', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_description', 'value' => 'Nền tảng học tập trực tuyến', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_logo', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_favicon', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_host', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_port', 'value' => '587', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_username', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_password', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_encryption', 'value' => 'tls', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_from_address', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_from_name', 'value' => 'EduPress LMS', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
