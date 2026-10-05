<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

use App\Domain\Shared\Money;

final readonly class OrderLine
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $thumbnailUrl,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        /**
         * The variant's SKU, as it was at the time of the order.
         *
         * A line keeps its own copy of the name and the price precisely
         * because the catalogue moves on; the reference is the same kind of
         * fact. It is what an ERP matches a line against to find the product
         * in its own stock, and nullable because Lunar does not force a
         * variant to carry one.
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
            'thumbnail_url' => $this->thumbnailUrl,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toArray(),
            'line_total' => $this->lineTotal->toArray(),
            'sku' => $this->sku,
        ];
    }
}
