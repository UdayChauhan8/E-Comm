<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Link to the parent order (if order is deleted, delete its items)
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Link to the product
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Product details snapshot at time of purchase
            $table->string('color');
            $table->string('size');
            $table->integer('quantity');

            // Price snapshot: What the customer actually paid at that exact moment
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2); // quantity * unit_price

            // Individual item status (e.g., 'pending', 'processing', 'shipped')
            $table->string('status')->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};