<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_reference',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'currency',
        'amount',
        'status',
        'failure_reason',
        'attempted_at',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            // Tell Laravel these are datetime columns, not plain strings
            // So you can do $payment->captured_at->diffForHumans() etc.
            'attempted_at' => 'datetime',
            'captured_at'  => 'datetime',
        ];
    }

    /**
     * Each payment belongs to one order.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}