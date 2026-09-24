<?php

declare(strict_types=1);

namespace App\Application\Review;

use App\Domain\Review\Port\ProductReviews;

/**
 * Whether this visitor may still write a review for this product: signed in,
 * and not already the author of one - pending or approved.
 */
final readonly class CanReviewProduct
{
    public function __construct(private ProductReviews $reviews) {}

    public function handle(int $productId, ?int $userId): bool
    {
        return $userId !== null && ! $this->reviews->hasReviewed($productId, $userId);
    }
}
