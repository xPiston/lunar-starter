<?php

declare(strict_types=1);

namespace App\Domain\Account\Port;

use App\Domain\Account\OrderSummary;
use App\Domain\Checkout\Order;

/**
 * Port SORTANT of the Account context.
 *
 * `listForCurrentUser()`/`findForCurrentUser()` are scoped to "the currently
 * authenticated user" by the adapter, not by a passed-in user id: the
 * caller (a use case, ultimately an `auth`-protected controller) never gets
 * to ask for someone else's orders, by construction rather than by a check
 * someone could forget to write.
 *
 * `findByReferenceForGuest()` is the one exception: it exists precisely to
 * serve someone who is NOT authenticated (guest checkout), so it is scoped
 * by a second piece of information only the order's owner would know (the
 * contact email given at checkout) instead of a session identity.
 */
interface OrderHistory
{
    /**
     * @return OrderSummary[]
     */
    public function listForCurrentUser(): array;

    public function findForCurrentUser(string $reference): ?Order;

    /**
     * Returns null both when no order matches the reference and when one
     * does but the email doesn't match - the two cases are indistinguishable
     * on purpose, so a wrong guess on either field never confirms that a
     * given order reference exists.
     */
    public function findByReferenceForGuest(string $reference, string $email): ?Order;
}
