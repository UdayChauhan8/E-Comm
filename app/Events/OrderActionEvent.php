<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderActionEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order,
        public User $user,
        public string $action) {}

}
