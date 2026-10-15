<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'color',
        'size',
        'quantity',
        'unit_price',
        'subtotal',
        'status',
    ];

    /**
     * Each order item belongs to one order.
     * Calling $item->order gives you the parent Order model.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Each order item links to one product.
     * Calling $item->product gives you the Product model.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}