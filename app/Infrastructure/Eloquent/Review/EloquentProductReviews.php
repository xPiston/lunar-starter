<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Review;

use App\Domain\Review\Port\ProductReviews;
use App\Domain\Review\RatingSummary;
use App\Domain\Review\Review;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ADAPTER: reviews stored in the application's own table.
 *
 * Under app/Infrastructure/Eloquent rather than .../Lunar for the same reason
 * as the editorial content: Lunar has no review concept, and this port would
 * be satisfied just as well by a third-party review service.
 */
final readonly class EloquentProductReviews implements ProductReviews
{
    public function listApproved(int $productId, int $limit = 20): array
    {
        return ProductReview::query()
            ->approved()
            ->where('product_id', $productId)
            ->with('user')
            ->latest('approved_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProductReview $review): Review => new Review(
                id: $review->id,
                rating: $review->rating,
                body: $review->body,
                authorName: $this->displayName($review),
                verifiedPurchase: $review->verified_purchase,
                // Approval date, not submission: it is the moment the review
                // became something the public can see.
                publishedAt: (string) $review->approved_at?->toIso8601String(),
            ))
            ->all();
    }

    public function ratingFor(int $productId): RatingSummary
    {
        // One grouped query for the whole summary: average, total and the
        // per-star breakdown all come from the same rows, and fetching them
        // separately would be three scans of the same index.
        $rows = ProductReview::query()
            ->approved()
            ->where('product_id', $productId)
            ->groupBy('rating')
            ->select('rating', DB::raw('count(*) as total'))
            ->pluck('total', 'rating');

        if ($rows->isEmpty()) {
            return RatingSummary::none();
        }

        $distribution = [];
        $count = 0;
        $sum = 0;

        foreach (range(1, 5) as $stars) {
            $given = (int) $rows->get($stars, 0);
            $distribution[$stars] = $given;
            $count += $given;
            $sum += $stars * $given;
        }

        return new RatingSummary(
            average: $count === 0 ? 0.0 : $sum / $count,
            count: $count,
            distribution: $distribution,
        );
    }

    public function hasReviewed(int $productId, int $userId): bool
    {
        // Deliberately not scoped to approved reviews: someone whose review
        // is still pending has reviewed this product, and telling them
        // otherwise would invite a second submission the unique index then
        // rejects with a database error.
        return ProductReview::query()
            ->where('product_id', $productId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function submit(int $productId, int $userId, int $rating, string $body, bool $verifiedPurchase): void
    {
        ProductReview::create([
            'product_id' => $productId,
            'user_id' => $userId,
            'rating' => $rating,
            'body' => $body,
            'verified_purchase' => $verifiedPurchase,
            // Pending: nothing reaches the storefront until a member of staff
            // approves it at /lunar/product-reviews.
            'approved_at' => null,
        ]);
    }

    /**
     * First name and last initial - enough to look like a person, without
     * publishing a customer's full name next to their opinions.
     */
    private function displayName(ProductReview $review): string
    {
        /** @var ?User $user */
        $user = $review->user;
        $name = trim((string) $user?->name);

        if ($name === '') {
            return 'Customer';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = array_shift($parts) ?? '';

        return $parts === []
            ? $first
            : $first.' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.';
    }
}
