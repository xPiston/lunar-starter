<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\Port\CartGateway;

final readonly class UpdateCartLineQuantity
{
    public function __construct(private CartGateway $cart) {}

    public function handle(int $cartLineId, int $quantity): Cart
    {
        return $this->cart->updateLine($cartLineId, $quantity);
    }
}
