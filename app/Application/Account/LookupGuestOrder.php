<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Checkout\Order;

final readonly class LookupGuestOrder
{
    public function __construct(private OrderHistory $orderHistory) {}

    /**
     * @throws OrderNotFoundException Reference unknown, or the email doesn't
     *                                match - deliberately the same exception for both, see
     *                                `OrderHistory::findByReferenceForGuest()`.
     */
    public function handle(string $reference, string $email): Order
    {
        return $this->orderHistory->findByReferenceForGuest($reference, $email)
            ?? throw OrderNotFoundException::forReference($reference);
    }
}
