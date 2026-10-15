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
        Schema::table('orders', function (Blueprint $table) {
            // 1. Reference number for the customer (e.g. "ORD-670D1234ABCD")
            // We make it nullable so existing rows in your database don't cause an error
            $table->string('reference')->nullable()->unique()->after('user_id');

            // 2. Currency (defaults to INR)
            $table->string('currency')->default('INR')->after('reference');

            // 3. Financial breakdown columns (all default to 0 so existing orders stay valid)
            $table->decimal('subtotal', 10, 2)->default(0)->after('currency');
            $table->decimal('discount', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 10, 2)->default(0)->after('discount');
            $table->decimal('total_amount', 10, 2)->default(0)->after('tax');

            // 4. Old legacy columns:
            // Since new orders will store products in 'order_items' and Razorpay IDs in 'payments',
            // we make the old columns nullable so they don't break when new orders are placed.
            $table->string('product_name')->nullable()->change();
            $table->string('order_number')->nullable()->change();
            $table->decimal('amount', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations (Rollback).
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop newly added columns if we ever rollback
            $table->dropColumn([
                'reference',
                'currency',
                'subtotal',
                'discount',
                'tax',
                'total_amount',
            ]);

            // Revert old columns back to NOT nullable
            $table->string('product_name')->nullable(false)->change();
            $table->string('order_number')->nullable(false)->change();
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });
    }
};