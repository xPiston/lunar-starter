<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Port;

use App\Domain\Checkout\CheckoutFailedException;
use App\Domain\Checkout\CheckoutState;
use App\Domain\Checkout\Country;
use App\Domain\Checkout\Order;
use App\Domain\Checkout\PaymentIntent;
use App\Domain\Checkout\ShippingOption;

/**
 * OUTBOUND port of the Checkout context.
 *
 * Only `App\Infrastructure\Lunar\Checkout\LunarCheckoutGateway` is allowed
 * to import Lunar code to implement this contract. The rest of the
 * application (use cases, Storefront controllers, Inertia pages) only
 * knows this interface.
 */
interface CheckoutGateway
{
    /**
     * @return Country[]
     */
    public function listCountries(): array;

    /**
     * @return ShippingOption[]
     */
    public function listShippingOptions(): array;

    public function currentState(): CheckoutState;

    /**
     * @param  array<string, mixed>  $address
     */
    public function setBillingAddress(array $address): void;

    /**
     * @param  array<string, mixed>  $address
     */
    public function setShippingAddress(array $address): void;

    public function selectShippingOption(string $identifier): void;

    public function startPayment(): PaymentIntent;

    /**
     * @throws CheckoutFailedException
     */
    public function completePayment(string $paymentIntentId): Order;
}
