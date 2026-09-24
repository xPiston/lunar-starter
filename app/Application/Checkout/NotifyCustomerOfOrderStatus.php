<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\OrderStatusChange;
use App\Mail\OrderStatusUpdatedMail;
use Illuminate\Support\Facades\Mail;

/**
 * Decides whether a status change is worth telling the customer about, and
 * sends it if so.
 *
 * The decision lives here rather than in the observer that notices the
 * change: what a shop announces is a business rule, and it is testable
 * without touching Lunar or an Eloquent event.
 */
final readonly class NotifyCustomerOfOrderStatus
{
    public function handle(OrderStatusChange $change): bool
    {
        if (! config('order_notifications.enabled')) {
            return false;
        }

        /** @var array<string, array<string, string>> $announced */
        $announced = config('order_notifications.statuses', []);
        $copy = $announced[$change->status->handle] ?? null;

        // Not every status is customer-facing. An unlisted one is a shop's
        // own bookkeeping, and silence is the correct behaviour.
        if ($copy === null) {
            return false;
        }

        Mail::to($change->email)->queue(new OrderStatusUpdatedMail($change, $copy));

        return true;
    }
}
