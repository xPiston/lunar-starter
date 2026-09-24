<?php

declare(strict_types=1);

namespace App\Domain\Review;

/**
 * One approved review, as the storefront shows it.
 *
 * Carries the author's display name, never their email or id: this object is
 * serialised into a public page, so what it does not hold is as deliberate as
 * what it does.
 */
final readonly class Review
{
    public function __construct(
        public int $id,
        public int $rating,
        public string $body,
        public string $authorName,
        public bool $verifiedPurchase,
        /** ISO 8601. */
        public string $publishedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'author_name' => $this->authorName,
            'verified_purchase' => $this->verifiedPurchase,
            'published_at' => $this->publishedAt,
        ];
    }
}
