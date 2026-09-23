<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Domain\Cart\CartRecovery;
use App\Domain\Cart\Port\CartGateway;

final readonly class RecoverCart
{
    public function __construct(private CartGateway $carts) {}

    public function handle(int $cartId, ?int $currentUserId): CartRecovery
    {
        return $this->carts->restore($cartId, $currentUserId);
    }
}
