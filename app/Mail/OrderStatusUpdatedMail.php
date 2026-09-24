<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Checkout\OrderStatusChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Takes the Checkout context's own VO, not a Lunar model - see
 * OrderConfirmationMail for the reasoning.
 *
 * @phpstan-type Copy array{subject: string, headline: string, body: string}
 */
final class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject: string, headline: string, body: string}  $copy
     */
    public function __construct(
        public readonly OrderStatusChange $change,
        public readonly array $copy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->copy['subject'].' - '.$this->change->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.status',
            with: [
                'change' => $this->change,
                'copy' => $this->copy,
                // Guests have no account to send them to, so everyone gets the
                // lookup page: it asks for the reference and the email the
                // order was placed with, which the recipient has by definition.
                'lookupUrl' => route('orders.lookup'),
            ],
        );
    }
}
