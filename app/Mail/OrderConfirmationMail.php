<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Checkout\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Takes the Checkout context's own Order VO, not a Lunar model: this class
 * lives outside app/Infrastructure/Lunar (it's a delivery mechanism, same
 * category as a controller), but still has no reason to know about Lunar.
 */
final class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order confirmation - {$this->order->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.confirmation',
            with: ['order' => $this->order],
        );
    }
}
