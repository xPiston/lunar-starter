<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

/**
 * An order has moved to a new status, and someone is waiting to hear about it.
 *
 * Carries the address to write to rather than a customer id: the recipient of
 * an order update is whoever gave their email at checkout, account or not.
 * Built by the infrastructure that noticed the change; read by the use case
 * that decides whether it is worth an email.
 */
final readonly class OrderStatusChange
{
    public function __construct(
        public string $reference,
        public OrderStatus $status,
        public string $email,
        public ?string $firstName,
    ) {}
}
