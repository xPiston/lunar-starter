<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\AbandonedCart;
use App\Domain\Cart\Port\CartReminders;
use App\Mail\AbandonedCartReminderMail;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one reminder per abandoned cart.
 *
 * Marking comes before queueing on purpose: if the mail driver throws, the
 * cart stays marked and nobody is emailed twice by the next hourly run. The
 * cost of that choice is a reminder that silently never arrives, which is the
 * better failure - a shop that emails a customer twice about the same cart
 * looks broken in a way the customer can see.
 */
final readonly class SendAbandonedCartReminders
{
    public function __construct(private CartReminders $reminders) {}

    /**
     * @return AbandonedCart[] The carts that were reminded.
     */
    public function handle(): array
    {
        if (! config('abandoned_carts.enabled')) {
            return [];
        }

        $now = new DateTimeImmutable;

        $carts = $this->reminders->listDue(
            idleSince: $now->modify('-'.(int) config('abandoned_carts.idle_after_minutes').' minutes'),
            notBefore: $now->modify('-'.(int) config('abandoned_carts.ignore_older_than_days').' days'),
            limit: (int) config('abandoned_carts.batch_size'),
        );

        foreach ($carts as $cart) {
            $this->reminders->markReminded($cart->id);

            Mail::to($cart->email)->queue(new AbandonedCartReminderMail($cart));
        }

        return $carts;
    }
}
