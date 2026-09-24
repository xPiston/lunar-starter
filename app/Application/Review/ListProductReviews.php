<?php

declare(strict_types=1);

namespace App\Application\Review;

use App\Domain\Review\Port\ProductReviews;
use App\Domain\Review\Review;

final readonly class ListProductReviews
{
    public function __construct(private ProductReviews $reviews) {}

    /**
     * @return Review[]
     */
    public function handle(int $productId, int $limit = 20): array
    {
        return $this->reviews->listApproved($productId, $limit);
    }
}
