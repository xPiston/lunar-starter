<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\Port\CartGateway;

final readonly class CountCartItems
{
    public function __construct(private CartGateway $cart) {}

    public function handle(): int
    {
        return $this->cart->currentItemCount();
    }
}
