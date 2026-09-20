<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\Port\CartGateway;

final readonly class RemoveCartLine
{
    public function __construct(private CartGateway $cart) {}

    public function handle(int $cartLineId): Cart
    {
        return $this->cart->removeLine($cartLineId);
    }
}
