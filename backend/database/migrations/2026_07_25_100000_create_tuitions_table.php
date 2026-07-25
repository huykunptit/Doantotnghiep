<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Học phí theo kỳ (BA 2026): mức cố định cho mỗi (sinh viên × học kỳ).
 * Thanh toán có thể gắn order PayOS qua order_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('unpaid'); // unpaid | paid
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuitions');
    }
};
