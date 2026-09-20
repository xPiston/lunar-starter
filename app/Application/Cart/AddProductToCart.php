<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\Port\CartGateway;

final readonly class AddProductToCart
{
    public function __construct(private CartGateway $cart) {}

    public function handle(int $productVariantId, int $quantity): Cart
    {
        return $this->cart->addLine($productVariantId, $quantity);
    }
}
