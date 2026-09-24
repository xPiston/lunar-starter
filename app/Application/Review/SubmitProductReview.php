<?php

declare(strict_types=1);

namespace App\Application\Review;

use App\Domain\Review\Port\ProductReviews;
use App\Domain\Review\Port\PurchaseCheck;

/**
 * Records a review, pending moderation.
 *
 * The two ports meet here: what someone wrote, and whether they bought the
 * thing. Neither storage knows about the other, and the badge shown next to a
 * review is decided once, at this moment, from the order history as it stands
 * then.
 */
final readonly class SubmitProductReview
{
    public function __construct(
        private ProductReviews $reviews,
        private PurchaseCheck $purchases,
    ) {}

    /**
     * @throws AlreadyReviewedException
     */
    public function handle(int $productId, int $userId, int $rating, string $body): void
    {
        if ($this->reviews->hasReviewed($productId, $userId)) {
            throw AlreadyReviewedException::make();
        }

        $this->reviews->submit(
            productId: $productId,
            userId: $userId,
            rating: $rating,
            body: $body,
            verifiedPurchase: $this->purchases->hasPurchased($userId, $productId),
        );
    }
}
