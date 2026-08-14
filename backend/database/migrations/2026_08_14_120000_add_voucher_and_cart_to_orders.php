<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('original_amount', 12, 0)->nullable()->after('amount');
            $table->decimal('discount_amount', 12, 0)->default(0)->after('original_amount');
            $table->foreignId('user_voucher_id')->nullable()->after('discount_amount')->constrained('user_vouchers')->nullOnDelete();
            $table->json('cart_items')->nullable()->after('user_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_voucher_id');
            $table->dropColumn(['original_amount', 'discount_amount', 'cart_items']);
        });
    }
};
