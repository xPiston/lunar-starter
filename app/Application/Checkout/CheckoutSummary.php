<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\CheckoutState;
use App\Domain\Checkout\Country;
use App\Domain\Checkout\PaymentIntent;
use App\Domain\Checkout\ShippingOption;

/**
 * Sortie agregee de `ShowCheckout`, propre a ce use case (contrairement aux
 * VOs de `App\Domain\Checkout\*`, reutilises par plusieurs use cases).
 */
final readonly class CheckoutSummary
{
    /**
     * @param  Country[]  $countries
     * @param  ShippingOption[]  $shippingOptions
     */
    public function __construct(
        public array $countries,
        public array $shippingOptions,
        public CheckoutState $state,
        public ?PaymentIntent $paymentIntent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'countries' => array_map(static fn (Country $country): array => $country->toArray(), $this->countries),
            'shipping_options' => array_map(
                static fn (ShippingOption $option): array => $option->toArray(),
                $this->shippingOptions,
            ),
            'state' => $this->state->toArray(),
            'payment_intent' => $this->paymentIntent?->toArray(),
        ];
    }
}
