<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Cart\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Takes the Cart context's own AbandonedCart VO, not a Lunar model - see
 * OrderConfirmationMail for the reasoning.
 *
 * Both links are signed URLs: the recovery one hands back a cart, and the
 * unsubscribe one writes to the database, so neither may be forgeable by
 * editing an id out of the address bar. They expire together with the
 * campaign rather than living forever.
 */
final class AbandonedCartReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly AbandonedCart $cart) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->cart->itemCount() === 1
                ? 'You left something behind'
                : 'You left '.$this->cart->itemCount().' items behind',
        );
    }

    public function content(): Content
    {
        $expiresAt = now()->addDays((int) config('abandoned_carts.link_lifetime_days'));

        return new Content(
            markdown: 'mail.cart.abandoned',
            with: [
                'cart' => $this->cart,
                'recoveryUrl' => URL::temporarySignedRoute('cart.recover', $expiresAt, ['cartId' => $this->cart->id]),
                'unsubscribeUrl' => URL::temporarySignedRoute('cart.reminders.unsubscribe', $expiresAt, ['email' => $this->cart->email]),
            ],
        );
    }
}
