<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Checkout\Order;

final readonly class ShowMyOrder
{
    public function __construct(private OrderHistory $orderHistory) {}

    /**
     * @throws OrderNotFoundException
     */
    public function handle(string $reference): Order
    {
        return $this->orderHistory->findForCurrentUser($reference)
            ?? throw OrderNotFoundException::forReference($reference);
    }
}
