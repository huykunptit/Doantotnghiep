<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete();
            $table->foreignId('cohort_id')->nullable()->constrained('cohorts')->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedSmallInteger('expected_graduation_year')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->string('status', 32)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['program_id', 'major_id']);
            $table->index(['cohort_id', 'status']);
            $table->index(['unit_id', 'status']);
            $table->index('advisor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_classes');
    }
};
