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
        $order = LunarOrder::with('lines')->find($orderId);

        if (! $order) {
            return;
        }

        DB::transaction(function () use ($order): void {
            // The insert is the lock. Two processes placing the same order at
            // once both reach here; the second one collides on the primary key
            // and stops, which is cheaper and more honest than a flag read
            // before a write.
            $claimed = DB::table('order_stock_commitments')->insertOrIgnore([
                'order_id' => $order->id,
                'committed_at' => now(),
            ]);

            if ($claimed === 0) {
                return;
            }

            /** @var OrderLine $line */
            foreach ($order->lines as $line) {
                foreach ($this->unitsFor($line) as $variantId => $quantity) {
                    $this->reduce($variantId, $quantity);
                }
            }
        });
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
     */
    private function reduce(int $variantId, int $quantity): void
    {
        ProductVariant::query()
            ->whereKey($variantId)
            // `always` is Lunar's own way of saying this product never runs
            // out - a download, a made-to-order piece. Counting it down would
            // put a number on something that has none.
            ->where('purchasable', '!=', 'always')
            ->decrement('stock', $quantity);
    }
}
