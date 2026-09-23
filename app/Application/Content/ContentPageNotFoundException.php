<?php

declare(strict_types=1);

namespace App\Application\Content;

use RuntimeException;

/**
 * Deliberately says nothing about which of "no such slug", "still a draft" or
 * "published in the future" happened: the storefront answers 404 to all
 * three, and a message that distinguished them would tell a visitor an
 * unpublished page exists.
 */
final class ContentPageNotFoundException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("No published content at [{$slug}].");
    }
}
