<?php

declare(strict_types=1);

namespace App\Application\Account;

use RuntimeException;

/**
 * APPLICATION exception (not domain): a missing order for a given reference
 * isn't a violated business rule, it's a missing (or not-yours) resource.
 * The controller translates it into a 404 - indistinguishable from "this
 * reference doesn't exist at all", which is deliberate: it doesn't leak
 * whether a reference belongs to someone else's order.
 */
final class OrderNotFoundException extends RuntimeException
{
    public static function forReference(string $reference): self
    {
        return new self("Order not found for reference \"{$reference}\".");
    }
}
