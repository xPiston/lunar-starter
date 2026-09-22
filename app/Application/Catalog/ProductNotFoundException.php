<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use RuntimeException;

/**
 * APPLICATION exception (not domain): a missing product for a given slug
 * isn't a violated business rule, it's a missing resource. The controller
 * translates it into a 404 response (Laravel already does this natively for
 * any unhandled exception that indirectly extends this via `abort(404)` -
 * here it's thrown explicitly from the use case).
 */
final class ProductNotFoundException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self("Product not found for slug \"{$slug}\".");
    }
}
