<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;

final readonly class ListPublishedProductSlugs
{
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * @return string[]
     */
    public function handle(): array
    {
        return $this->catalog->listPublishedSlugs();
    }
}
