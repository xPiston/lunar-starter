<?php

declare(strict_types=1);

namespace App\Application\Review;

use RuntimeException;

/**
 * Raised when someone reviews a product they have already reviewed. The
 * message is shown to that person, so it says plainly what happened - there
 * is nothing to protect here: they are being told about their own review.
 */
final class AlreadyReviewedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('You have already reviewed this product.');
    }
}
