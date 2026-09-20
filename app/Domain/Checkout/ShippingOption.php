<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

use App\Domain\Shared\Money;

final readonly class ShippingOption
{
    public function __construct(
        public string $identifier,
        public string $name,
        public ?string $description,
        public Money $price,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price->toArray(),
        ];
    }
}
