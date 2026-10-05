<?php

namespace App\Events;

use App\Models\User;
use App\Models\Order;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderActionEvent
{
    use Dispatchable, SerializesModels;


    public function __construct(public Order $order,
        public User $user,
        public string $action)
    {
        
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
