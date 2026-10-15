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
'created' => 'New Order Created - '.$this->order->reference,
'updated' => 'Order Updated - '.$this->order->reference,
'deleted' => 'Order Deleted - '.$this->order->reference,
'paid'    => 'Payment Confirmed - '.$this->order->reference,
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
