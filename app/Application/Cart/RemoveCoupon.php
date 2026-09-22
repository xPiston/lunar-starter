<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\Port\CartGateway;

final readonly class RemoveCoupon
{
    public function __construct(private CartGateway $cart) {}

    public function handle(): Cart
    {
        return $this->cart->removeCoupon();
    }
}
