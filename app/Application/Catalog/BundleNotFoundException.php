<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use RuntimeException;

final class BundleNotFoundException extends RuntimeException
{
    public static function forSlug(string $slug): never
    {
        throw new self("No bundle on offer at [{$slug}].");
    }
}
