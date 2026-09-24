<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Review;

use App\Domain\Review\Port\PurchaseCheck;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

/**
 * ADAPTER: answers "did they buy it" from Lunar's order history.
 *
 * A Lunar adapter, unlike the reviews themselves: orders are Lunar's, and
 * this is the only file in the Review context that knows it.
 */
final readonly class LunarPurchaseCheck implements PurchaseCheck
{
    public function hasPurchased(int $userId, int $productId): bool
    {
        // Order lines point at a variant, not a product, so the match goes
        // through the variants of that product. Any of them counts: someone
        // who bought the medium reviewed the product, not the size.
        $variantIds = ProductVariant::query()
            ->where('product_id', $productId)
            ->pluck('id');

        if ($variantIds->isEmpty()) {
            return false;
        }

        return Order::query()
            ->where('user_id', $userId)
            // Placed, not abandoned: Lunar stamps `placed_at` when checkout
            // completes, and a cart that never got that far is not a purchase.
            ->whereNotNull('placed_at')
            ->whereHas('lines', fn ($lines) => $lines
                ->where('purchasable_type', (new ProductVariant)->getMorphClass())
                ->whereIn('purchasable_id', $variantIds)
            )
            ->exists();
    }
}
