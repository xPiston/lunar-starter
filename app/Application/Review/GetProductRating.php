<?php

declare(strict_types=1);

namespace App\Application\Review;

use App\Domain\Review\Port\ProductReviews;
use App\Domain\Review\RatingSummary;

final readonly class GetProductRating
{
    public function __construct(private ProductReviews $reviews) {}

    public function handle(int $productId): RatingSummary
    {
        return $this->reviews->ratingFor($productId);
    }
}
