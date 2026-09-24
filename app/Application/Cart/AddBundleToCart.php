<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLineException;
use App\Domain\Cart\Port\CartGateway;

final readonly class AddBundleToCart
{
    public function __construct(private CartGateway $cart) {}

    /**
     * @throws CartLineException
     */
    public function handle(int $bundleId, int $quantity = 1): Cart
    {
        return $this->cart->addBundle($bundleId, $quantity);
    }
}
