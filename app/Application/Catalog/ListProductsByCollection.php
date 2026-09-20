<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\ProductSummary;

final readonly class ListProductsByCollection
{
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * @return ProductSummary[]
     */
    public function handle(string $collectionSlug, int $limit = 24): array
    {
        return $this->catalog->listByCollection($collectionSlug, $limit);
    }
}
