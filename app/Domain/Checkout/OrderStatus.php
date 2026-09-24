<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

/**
 * Where an order has got to.
 *
 * A handle and a label rather than an enum: the set of statuses a shop uses
 * is configuration, not code - a store adds "delivered" or "returned" without
 * touching this. The handle is what logic keys on, the label is what a
 * customer reads.
 */
final readonly class OrderStatus
{
    public function __construct(
        public string $handle,
        public string $label,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'label' => $this->label,
        ];
    }
}
