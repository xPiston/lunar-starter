<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\PaymentIntent;
use App\Domain\Checkout\Port\CheckoutGateway;

final readonly class StartPayment
{
    public function __construct(private CheckoutGateway $gateway) {}

    public function handle(): PaymentIntent
    {
        return $this->gateway->startPayment();
    }
}
