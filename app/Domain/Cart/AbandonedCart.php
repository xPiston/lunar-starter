<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Shared\Money;

/**
 * A cart someone left behind, with everything needed to write to them about
 * it: who to reach, what was in it, and what it came to.
 *
 * Separate from `Cart` because it answers a different question. `Cart` is
 * "what is in front of this visitor right now" and has no owner - this one
 * exists precisely because there is no visitor any more, only an address.
 */
final readonly class AbandonedCart
{
    /**
     * @param  string  $email  Where the reminder goes: the account's address, or the
     *                         one given at checkout before the cart was left.
     * @param  ?string  $firstName  For the greeting. Null for an account that never
     *                              got as far as an address.
     * @param  CartLine[]  $lines
     */
    public function __construct(
        public int $id,
        public string $email,
        public ?string $firstName,
        public array $lines,
        public Money $total,
    ) {}

    public function itemCount(): int
    {
        return array_sum(array_map(
            static fn (CartLine $line): int => $line->quantity,
            $this->lines,
        ));
    }
}
