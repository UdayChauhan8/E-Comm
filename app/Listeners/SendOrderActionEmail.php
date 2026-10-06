<?php

namespace App\Listeners;

use App\Events\OrderActionEvent;
use App\Mail\OrderActionMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderActionEmail implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    // public function __construct()
    // {
    //     //
    // }

    /**
     * Handle the event.
     */
    public function handle(OrderActionEvent $event): void
    {
        Mail::to($event->user->email)->send(new OrderActionMail($event->order, $event->action));
    }
}
