<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\Port\CheckoutGateway;

final readonly class SetShippingAddress
{
    public function __construct(private CheckoutGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $address
     */
    public function handle(array $address): void
    {
        $this->gateway->setShippingAddress($address);
    }
}
