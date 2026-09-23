<?php

declare(strict_types=1);

namespace App\Domain\Cart;

/**
 * What happened when someone followed a recovery link.
 *
 * Three outcomes rather than a boolean, because the storefront has to react
 * differently to each: a cart that is gone is a dead end, while one belonging
 * to an account is recoverable as soon as its owner signs in.
 */
enum CartRecovery
{
    /** The cart is back in this browser's session. */
    case Restored;

    /** Bought, emptied, merged into another cart or deleted since. */
    case Unavailable;

    /**
     * The cart belongs to an account and whoever opened the link isn't
     * signed in as that account. Forwarded mail is the ordinary way this
     * happens, and the cart carries the address given at checkout - so it is
     * handed over only to the person who can prove it is theirs.
     */
    case RequiresLogin;
}
