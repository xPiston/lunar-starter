<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\InvalidCouponException;
use App\Domain\Cart\Port\CartGateway;

final readonly class ApplyCoupon
{
    public function __construct(private CartGateway $cart) {}

    /**
     * @throws InvalidCouponException
     */
    public function handle(string $code): Cart
    {
        return $this->cart->applyCoupon($code);
    }
}
