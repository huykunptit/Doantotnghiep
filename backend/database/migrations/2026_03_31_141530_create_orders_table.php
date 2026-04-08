<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 0); // VND snapshot at purchase time
            // pending | completed | failed | refunded
            $table->string('status')->default('pending');
            // vnpay | momo | zalopay | bank_transfer | free
            $table->string('payment_method')->default('vnpay');
            $table->string('payment_ref')->nullable()->unique(); // gateway transaction ref
            $table->json('gateway_response')->nullable(); // raw response for audit
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('payment_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
