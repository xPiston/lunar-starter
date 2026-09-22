<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Domain\Account\OrderSummary;
use App\Domain\Account\Port\OrderHistory;

final readonly class ListMyOrders
{
    public function __construct(private OrderHistory $orderHistory) {}

    /**
     * @return OrderSummary[]
     */
    public function handle(): array
    {
        return $this->orderHistory->listForCurrentUser();
    }
}
