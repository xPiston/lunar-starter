<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Inventory\Port\StockLedger;
use Lunar\Models\Order as LunarOrder;

/**
 * Keeps the shelf in step with what the orders say.
 *
 * An observer rather than a line in the checkout use case, for the reason
 * OrderStatusObserver gives next to it: an order placed from the admin panel,
 * a console command or an import is just as placed as one paid for on the
 * storefront, and all of them are a model update in the end.
 *
 * Two moments matter. Placement takes the units; moving into one of the
 * statuses in config/inventory.php gives them back, and moving back out takes
 * them again. The ledger decides what actually happens - this only notices.
 */
final readonly class OrderStockObserver
{
    public function __construct(private StockLedger $stock) {}

    public function updated(LunarOrder $order): void
    {
        // After the save, and only on a transition. An order re-saved
        // unchanged - which the admin panel does on any edit - must not move
        // the shelf. The ledger refuses it anyway; this avoids asking.
        if ($order->wasChanged('placed_at') && $order->placed_at !== null) {
            $this->stock->commitOrder((int) $order->id);

            return;
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        // An order that never completed checkout took nothing, so it has
        // nothing to give back whatever its status says.
        if ($order->placed_at === null) {
            return;
        }

        $this->restocks((string) $order->status)
            ? $this->stock->releaseOrder((int) $order->id)
            // Back out of a cancellation: whatever was given back is taken
            // again, because the order is going to happen after all.
            : $this->stock->commitOrder((int) $order->id);
    }

    /**
     * An order created already placed - a fixture, an import, a shop that
     * records a sale made over the counter - never passes through `updated`.
     */
    public function created(LunarOrder $order): void
    {
        if ($order->placed_at === null || $this->restocks((string) $order->status)) {
            return;
        }

        $this->stock->commitOrder((int) $order->id);
    }

    private function restocks(string $status): bool
    {
        /** @var array<int, string> $statuses */
        $statuses = config('inventory.restock_statuses', []);

        return in_array($status, $statuses, true);
    }
}
