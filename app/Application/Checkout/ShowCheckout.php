<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\CheckoutState;
use App\Domain\Checkout\Port\CheckoutGateway;

final readonly class ShowCheckout
{
    public function __construct(private CheckoutGateway $gateway) {}

    public function handle(): CheckoutSummary
    {
        $state = $this->gateway->currentState();

        return new CheckoutSummary(
            countries: $this->gateway->listCountries(),
            shippingOptions: $this->gateway->listShippingOptions(),
            state: $state,
            paymentIntent: $this->readyForPayment($state) ? $this->gateway->startPayment() : null,
        );
    }

    private function readyForPayment(CheckoutState $state): bool
    {
        return $state->billingAddress !== null && $state->selectedShippingOption !== null;
    }
}
