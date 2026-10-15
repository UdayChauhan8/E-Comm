<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Which order this payment attempt belongs to
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Our internal payment reference (e.g. "PAY-670D1234ABCD")
            $table->string('payment_reference')->unique();

            // Payment gateway used ('razorpay', 'phonepe', etc.)
            $table->string('gateway')->default('razorpay');

            // Razorpay's specific identifiers for this attempt
            $table->string('gateway_order_id')->nullable()->unique();    // order_xxx
            $table->string('gateway_payment_id')->nullable();            // pay_xxx (filled after payment)

            $table->string('currency')->default('INR');
            $table->decimal('amount', 10, 2);

            // Payment status: 'pending', 'captured', 'failed', 'refunded'
            $table->string('status')->default('pending');

            // Reason if bank/gateway rejected it
            $table->string('failure_reason')->nullable();

            // Audit timestamps
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};