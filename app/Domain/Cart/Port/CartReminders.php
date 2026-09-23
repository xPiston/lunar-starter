<?php

declare(strict_types=1);

namespace App\Domain\Cart\Port;

use App\Domain\Cart\AbandonedCart;
use DateTimeImmutable;

/**
 * PORT: finding carts worth writing about, and remembering what was sent.
 *
 * Every rule about what counts as abandoned lives behind `listDue()` rather
 * than at the call site - the use case asks for carts to remind and gets only
 * carts it is allowed to remind. A completed, emptied, merged, unreachable,
 * already-reminded or unsubscribed cart never comes out of here, so no caller
 * can forget one of those cases.
 */
interface CartReminders
{
    /**
     * Carts left untouched since `$idleSince`, created no earlier than
     * `$notBefore`, reachable by email, and not yet reminded.
     *
     * @return AbandonedCart[]
     */
    public function listDue(DateTimeImmutable $idleSince, DateTimeImmutable $notBefore, int $limit): array;

    /**
     * Records that this cart has had its reminder, so the next run skips it.
     */
    public function markReminded(int $cartId): void;

    /**
     * Stops every future reminder to this address, whatever cart it is
     * attached to.
     */
    public function optOut(string $email): void;
}
