<?php

declare(strict_types=1);

namespace App\Domain\Erp;

use RuntimeException;
use Throwable;

/**
 * The ERP could not be reached, or answered with something that is not an
 * answer.
 *
 * This is the RETRYABLE failure, and the distinction matters to the job that
 * catches it. An ERP being down, slow or mid-upgrade is a normal Tuesday and
 * the order should go out later. A payload the ERP rejects on its merits -
 * a missing mandatory field, a customer code the ERP will never accept - will
 * be rejected identically on every retry, so it is left to surface as itself.
 */
final class ErpUnavailableException extends RuntimeException
{
    public static function transport(string $driver, string $reason, ?Throwable $previous = null): self
    {
        return new self("The {$driver} ERP could not be reached: {$reason}", previous: $previous);
    }

    public static function refused(string $driver, string $call, int $status, string $body): self
    {
        return new self(sprintf(
            'The %s ERP refused %s with HTTP %d: %s',
            $driver,
            $call,
            $status,
            mb_substr(trim($body), 0, 300),
        ));
    }
}
