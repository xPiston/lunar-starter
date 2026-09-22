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
        ];
    }
}
