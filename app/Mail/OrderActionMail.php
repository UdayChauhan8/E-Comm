<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderActionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $action
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'created' => 'New Order Created - ' . $this->order->order_number,
            'updated' => 'Order Updated - ' . $this->order->order_number,
            'deleted' => 'Order Deleted - ' . $this->order->order_number,
        ];

        return new Envelope(
            subject: $subjects[$this->action] ?? 'Order Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-action',
        );
    }
}