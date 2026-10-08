<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Inventory\Port\StockLedger;
use Lunar\Models\Order as LunarOrder;

/**
 * Notices that an order has been placed and takes its stock off the shelf.
 *
 * An observer rather than a line in the checkout use case, for the reason
 * OrderStatusObserver gives next to it: an order placed from the admin panel,
 * a console command or a webhook is just as placed as one paid for on the
 * storefront, and all of them are a model update in the end.
 *
 * `placed_at` rather than a status: a shop renames its statuses, and Lunar's
 * own payment flow sets this field at the moment the money is confirmed.
 */
final readonly class OrderPlacedObserver
{
    public function __construct(private StockLedger $stock) {}

    public function updated(LunarOrder $order): void
    {
        // After the save, and only on the transition. An order re-saved with
        // the same `placed_at` - which the admin panel does on any edit - must
        // not take its stock a second time. The ledger refuses it anyway; this
        // just avoids asking.
        if (! $order->wasChanged('placed_at') || $order->placed_at === null) {
            return;
        }

        $this->stock->commitOrder((int) $order->id);
    }

    /**
     * An order created already placed - a fixture, an import, a shop that
     * records a sale made over the counter - never passes through `updated`.
     */
    public function created(LunarOrder $order): void
    {
        if ($order->placed_at === null) {
            return;
        }

        $this->stock->commitOrder((int) $order->id);
    }
}
