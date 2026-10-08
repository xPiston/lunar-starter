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
        /**
         * What is on the line, as a stable string.
         *
         * Still not a variant id - that is the point. It is the purchasable's
         * own identifier: a variant's SKU, a bundle's slug, and whatever a
         * future purchasable decides to answer. The line stays indifferent to
         * which it got.
         *
         * It is the same value Lunar copies onto `order_lines.identifier`
         * when the order is placed (see its CreateOrderLines pipeline), so a
         * cart line and the order line it becomes carry the same reference -
         * which is what lets anything downstream follow one product from the
         * basket to the sale.
         *
         * Nullable because nothing forces a variant to carry a SKU.
         */
        public ?string $sku = null,
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
            'sku' => $this->sku,
        ];
    }
}
