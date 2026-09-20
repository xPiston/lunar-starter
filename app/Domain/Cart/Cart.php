<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\Money;

final readonly class Cart
{
    /**
     * @param  CartLine[]  $lines
     */
    public function __construct(
        public ?int $id,
        public array $lines,
        public Money $subTotal,
        public Money $shippingTotal,
        public Money $discountTotal,
        public Money $taxTotal,
        public Money $total,
        public ?string $couponCode,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lines' => array_map(
                static fn (CartLine $line): array => $line->toArray(),
                $this->lines,
            ),
            'sub_total' => $this->subTotal->toArray(),
            'shipping_total' => $this->shippingTotal->toArray(),
            'discount_total' => $this->discountTotal->toArray(),
            'tax_total' => $this->taxTotal->toArray(),
            'total' => $this->total->toArray(),
            'coupon_code' => $this->couponCode,
        ];
    }
}
