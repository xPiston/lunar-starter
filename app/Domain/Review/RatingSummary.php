<?php

declare(strict_types=1);

namespace App\Domain\Review;

/**
 * What a product's approved reviews add up to.
 *
 * `count` is what makes the average readable: 5.0 from one review and 4.6
 * from two hundred are not the same claim, and structured data that omits the
 * count is rejected by search engines for exactly that reason.
 */
final readonly class RatingSummary
{
    /**
     * @param  array<int, int>  $distribution  How many reviews gave each rating, 1 to 5.
     */
    public function __construct(
        public float $average,
        public int $count,
        public array $distribution = [],
    ) {}

    public static function none(): self
    {
        return new self(0.0, 0, array_fill_keys(range(1, 5), 0));
    }

    public function hasReviews(): bool
    {
        return $this->count > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'average' => round($this->average, 2),
            'count' => $this->count,
            'distribution' => $this->distribution,
        ];
    }
}
