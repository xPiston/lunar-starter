<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Port;

use App\Domain\Catalog\Bundle;

/**
 * PORT: the bundles a shop offers.
 *
 * Separate from ProductCatalog because a bundle is not a product: it is an
 * offer made of them, priced on its own and limited by its scarcest part.
 * Read-only - bundles are composed in the back office.
 *
 * Only active bundles come out of here, so no caller can put a retired offer
 * in front of a customer.
 */
interface BundleCatalog
{
    /**
     * @return Bundle[]
     */
    public function listActive(): array;

    public function findBySlug(string $slug): ?Bundle;
}
