<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/**
 * "Buy three, pay less each": one step of a product's quantity pricing.
 *
 * Carries both the unit price and what the whole pack comes to, because a
 * customer choosing between packs compares totals, then checks the per-unit
 * figure to see the offer. Working either out on the frontend would mean
 * doing money arithmetic in JavaScript, which is how rounding errors reach a
 * price label.
 */
final readonly class PriceTier
{
    public function __construct(
        /** How many units this step starts at. 1 is the ordinary price. */
        public int $quantity,
        public Money $unitPrice,
        /** What `quantity` units cost at this step. */
        public Money $total,
        /** What the same units would cost at the single-unit price. */
        public Money $undiscountedTotal,
        /** Rounded, for a badge. 0 on the first step. */
        public int $percentOff,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toArray(),
            'total' => $this->total->toArray(),
            'undiscounted_total' => $this->undiscountedTotal->toArray(),
            'percent_off' => $this->percentOff,
        ];
    }
}
