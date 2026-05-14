<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: lớp tín chỉ nào dạy cho lớp hành chính nào
        Schema::create('administrative_class_class_section', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('administrative_class_id');
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedTinyInteger('term_number')->nullable(); // kỳ thứ mấy của CTĐT
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('administrative_class_id', 'acs_admin_class_fk')
                ->references('id')->on('administrative_classes')->cascadeOnDelete();
            $table->foreign('class_section_id', 'acs_class_section_fk')
                ->references('id')->on('class_sections')->cascadeOnDelete();
            $table->foreign('assigned_by', 'acs_assigned_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(
                ['administrative_class_id', 'class_section_id'],
                'admin_class_section_unique'
            );
            $table->index(['administrative_class_id', 'term_number'], 'admin_class_term_idx');
            $table->index('class_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_class_class_section');
    }
};
