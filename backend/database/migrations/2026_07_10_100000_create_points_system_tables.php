<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // User points balance + streak
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('points_balance')->default(0)->after('id');
            $table->unsignedInteger('streak_days')->default(0)->after('points_balance');
            $table->date('last_login_date')->nullable()->after('streak_days');
            $table->date('streak_last_updated')->nullable()->after('last_login_date');
        });

        // Points transaction log
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // earn, redeem
            $table->string('action'); // login_daily, course_complete, exam_high_score, purchase, streak_bonus, survey, review_course
            $table->bigInteger('amount'); // positive = earn, negative = redeem
            $table->string('description');
            $table->nullableMorphs('referenceable'); // polymorphic: Course, Exam, Order, etc.
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });

        // Voucher templates (admin creates)
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // discount_percent, discount_fixed, free_course, physical_gift, ai_quota
            $table->integer('discount_value')->nullable(); // percent or fixed VND
            $table->unsignedBigInteger('points_cost'); // points needed to redeem
            $table->unsignedInteger('total_quantity')->nullable(); // null = unlimited
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete(); // for free_course type
            $table->timestamps();

            $table->index('is_active');
            $table->index('type');
        });

        // User redeemed vouchers
        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique(); // generated redemption code
            $table->string('status')->default('unused'); // unused, used, expired
            $table->bigInteger('points_spent');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vouchers');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('point_transactions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'points_balance',
                'streak_days',
                'last_login_date',
                'streak_last_updated',
            ]);
        });
    }
};
