<?php

declare(strict_types=1);

namespace App\Domain\Review\Port;

/**
 * PORT: has this customer actually bought this product?
 *
 * Its own port rather than a method on ProductReviews, because the answer
 * lives in the order history - a different context, and in this template a
 * different storage engine entirely. The review use case composes the two,
 * which is the whole point of ports: the "verified purchase" badge is a
 * decision, not a join.
 */
interface PurchaseCheck
{
    public function hasPurchased(int $userId, int $productId): bool;
}
