<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_card_number', 20)->nullable()->after('phone');
            $table->string('gender', 16)->nullable()->after('id_card_number');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('nationality', 64)->default('Việt Nam')->after('date_of_birth');
            $table->string('hometown')->nullable()->after('nationality');
            $table->string('permanent_address')->nullable()->after('hometown');
            $table->string('study_status', 32)->nullable()->after('permanent_address');

            $table->unique('id_card_number');
            $table->index('study_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['id_card_number']);
            $table->dropIndex(['study_status']);
            $table->dropColumn([
                'id_card_number',
                'gender',
                'date_of_birth',
                'nationality',
                'hometown',
                'permanent_address',
                'study_status',
            ]);
        });
    }
};
