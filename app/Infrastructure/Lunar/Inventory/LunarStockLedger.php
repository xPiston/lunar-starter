<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Inventory;

use App\Domain\Inventory\Port\StockLedger;
use App\Models\ProductBundle;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order as LunarOrder;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

/**
 * Lunar does not manage inventory.
 *
 * Its core writes the `stock` column in exactly one file - the model that
 * declares it - and nothing anywhere reduces it when an order is placed.
 * `ValidateCartForOrderCreation`, the last check before the money is taken,
 * calls `isPurchasable()`, which looks at whether the variant is trashed and
 * its product published, and never at stock.
 *
 * So the figure the storefront shows as "Only 2 left" only ever moves when
 * somebody edits it in the admin panel. Without this class, two customers -
 * or fifty - each buy the last unit, and nothing anywhere says so.
 */
final readonly class LunarStockLedger implements StockLedger
{
    public function commitOrder(int $orderId): void
    {
        $this->move($orderId, committing: true);
    }

    public function releaseOrder(int $orderId): void
    {
        $this->move($orderId, committing: false);
    }

    /**
     * Shelf in one direction or the other, once.
     *
     * The write that changes the commitment row is also what claims the
     * right to move the stock: both are conditional on the state the caller
     * believes it is in, so two processes cancelling the same order at the
     * same moment cannot both give the units back. A row read followed by a
     * decision would let them.
     */
    private function move(int $orderId, bool $committing): void
    {
        $order = LunarOrder::with('lines')->find($orderId);

        if (! $order) {
            return;
        }

        DB::transaction(function () use ($order, $committing): void {
            if (! $this->claim((int) $order->id, $committing)) {
                return;
            }

            /** @var OrderLine $line */
            foreach ($order->lines as $line) {
                foreach ($this->unitsFor($line) as $variantId => $quantity) {
                    $this->shift($variantId, $committing ? -$quantity : $quantity);
                }
            }
        });
    }

    /**
     * Whether this call is the one that gets to move the stock.
     *
     * Committing has two ways in: an order never seen before, and one whose
     * units were given back and are being taken again. Releasing has one, and
     * only from a commitment that is still standing.
     */
    private function claim(int $orderId, bool $committing): bool
    {
        $commitments = DB::table('order_stock_commitments');

        if (! $committing) {
            return (clone $commitments)
                ->where('order_id', $orderId)
                ->whereNull('released_at')
                ->update(['released_at' => now()]) === 1;
        }

        $inserted = (clone $commitments)->insertOrIgnore([
            'order_id' => $orderId,
            'committed_at' => now(),
            'released_at' => null,
        ]);

        if ($inserted === 1) {
            return true;
        }

        return (clone $commitments)
            ->where('order_id', $orderId)
            ->whereNotNull('released_at')
            ->update(['committed_at' => now(), 'released_at' => null]) === 1;
    }

    /**
     * How many of each variant one order line consumes.
     *
     * A line holds a variant or one of this application's bundles, and a
     * bundle is several variants at once - three of one and one of another,
     * times however many boxes were ordered. Shipping lines and discounts are
     * order lines too, and hold nothing purchasable at all.
     *
     * @return array<int, int> variant id => quantity
     */
    private function unitsFor(OrderLine $line): array
    {
        $purchasable = $line->purchasable;

        if ($purchasable instanceof ProductVariant) {
            return [$purchasable->id => $line->quantity];
        }

        if ($purchasable instanceof ProductBundle) {
            $units = [];

            foreach ($purchasable->items as $item) {
                $variantId = (int) $item->product_variant_id;
                $units[$variantId] = ($units[$variantId] ?? 0) + ($item->quantity * $line->quantity);
            }

            return $units;
        }

        return [];
    }

    /**
     * A single atomic statement, not a read followed by a write: two orders
     * for the last unit must each take one, even when they land at the same
     * moment.
     *
     * `$by` is negative when selling and positive when giving back.
     */
    private function shift(int $variantId, int $by): void
    {
        $query = ProductVariant::query()
            ->whereKey($variantId)
            // `always` is Lunar's own way of saying this product never runs
            // out - a download, a made-to-order piece. Counting it down would
            // put a number on something that has none, and counting it back
            // up would invent one.
            ->where('purchasable', '!=', 'always');

        $by < 0
            ? $query->decrement('stock', -$by)
            : $query->increment('stock', $by);
    }
}
