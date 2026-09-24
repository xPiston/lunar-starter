<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

use App\Domain\Shared\Money;

final readonly class Order
{
    /**
     * @param  OrderLine[]  $lines
     */
    public function __construct(
        public int $id,
        public string $reference,
        public bool $placed,
        public OrderStatus $status,
        public array $lines,
        public ?Address $shippingAddress,
        public ?Address $billingAddress,
        public Money $subTotal,
        public Money $shippingTotal,
        public Money $discountTotal,
        public Money $taxTotal,
        public Money $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->toArray(),
            'placed' => $this->placed,
            'lines' => array_map(
                static fn (OrderLine $line): array => $line->toArray(),
                $this->lines,
            ),
            'shipping_address' => $this->shippingAddress?->toArray(),
            'billing_address' => $this->billingAddress?->toArray(),
            'sub_total' => $this->subTotal->toArray(),
            'shipping_total' => $this->shippingTotal->toArray(),
            'discount_total' => $this->discountTotal->toArray(),
            'tax_total' => $this->taxTotal->toArray(),
            'total' => $this->total->toArray(),
        ];
    }
}
