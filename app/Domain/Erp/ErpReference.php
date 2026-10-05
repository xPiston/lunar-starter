<?php

declare(strict_types=1);

namespace App\Domain\Erp;

/**
 * What the ERP calls the things the shop just sent it.
 *
 * Ids are strings even where both shipped ERPs use integers: the next adapter
 * may well hand back a UUID or a document number, and this value object has no
 * reason to care which.
 *
 * `alreadyPresent` carries the one fact worth reading in a log: whether this
 * call created something or found it already there. A retry that reports
 * `true` is the idempotency working, not a duplicate.
 */
final readonly class ErpReference
{
    public function __construct(
        public string $driver,
        public string $customerId,
        public string $orderId,
        public bool $alreadyPresent = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'customer_id' => $this->customerId,
            'order_id' => $this->orderId,
            'already_present' => $this->alreadyPresent,
        ];
    }
}
