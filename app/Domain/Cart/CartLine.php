<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\Money;

/**
 * A line of the cart, whatever is on it.
 *
 * Deliberately says nothing about variants: a line can hold a single product
 * or a bundle of several, and the cart page treats them identically. What
 * distinguishes them is `options` - the size of a shirt, or the contents of a
 * box.
 */
final readonly class CartLine
{
    public function __construct(
        public int $id,
        public string $name,
        /** "M", or "1x Tee (S), 2x Socks" - empty when there is nothing to add. */
        public string $options,
        public ?string $thumbnailUrl,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'options' => $this->options,
            'thumbnail_url' => $this->thumbnailUrl,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toArray(),
            'line_total' => $this->lineTotal->toArray(),
        ];
    }
}
