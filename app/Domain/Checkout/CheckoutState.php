<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

/**
 * Current state of the cart with respect to checkout: what has already
 * been filled in, to pre-fill the page on every reload.
 */
final readonly class CheckoutState
{
    public function __construct(
        public ?Address $billingAddress,
        public ?Address $shippingAddress,
        public ?string $selectedShippingOption,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'billing_address' => $this->billingAddress?->toArray(),
            'shipping_address' => $this->shippingAddress?->toArray(),
            'selected_shipping_option' => $this->selectedShippingOption,
        ];
    }
}
