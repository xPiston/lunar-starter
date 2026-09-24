<?php

declare(strict_types=1);

namespace App\Domain\Review\Port;

use App\Domain\Review\RatingSummary;
use App\Domain\Review\Review;

/**
 * PORT: what customers said about a product.
 *
 * Reading and writing sit on the same port because, unlike the editorial
 * content, the storefront is where reviews are written - the back office only
 * decides which ones become visible.
 *
 * "Approved" is this port's responsibility. Nothing pending comes out of
 * `listApproved()` or counts towards `ratingFor()`, so no caller can publish
 * an unmoderated review by forgetting a condition.
 */
interface ProductReviews
{
    /**
     * @return Review[] Newest first.
     */
    public function listApproved(int $productId, int $limit = 20): array;

    public function ratingFor(int $productId): RatingSummary;

    public function hasReviewed(int $productId, int $userId): bool;

    /**
     * Records a review, pending approval.
     *
     * `$verifiedPurchase` is passed in rather than looked up here: whether
     * someone bought the product is the Checkout context's knowledge, and
     * this port has no business querying orders.
     */
    public function submit(int $productId, int $userId, int $rating, string $body, bool $verifiedPurchase): void;
}
