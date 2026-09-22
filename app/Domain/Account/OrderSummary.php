<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Shared\Money;

/**
 * Lightweight row for the order history list. `App\Domain\Checkout\Order`
 * (full detail: lines, addresses) is reused as-is for viewing a single past
 * order - no need for a second, near-duplicate VO.
 */
final readonly class OrderSummary
{
    public function __construct(
        public string $reference,
        public string $placedAt,
        public string $status,
        public int $itemCount,
        public Money $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'placed_at' => $this->placedAt,
            'status' => $this->status,
            'item_count' => $this->itemCount,
            'total' => $this->total->toArray(),
        ];
    }
}
