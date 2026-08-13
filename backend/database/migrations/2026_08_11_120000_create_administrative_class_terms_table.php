<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_class_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrative_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number');
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['administrative_class_id', 'term_number'], 'admin_class_terms_class_id_term_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_class_terms');
    }
};
