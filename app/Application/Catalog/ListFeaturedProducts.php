<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\ProductSummary;

final readonly class ListFeaturedProducts
{
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * @return ProductSummary[]
     */
    public function handle(int $limit = 8): array
    {
        return $this->catalog->listFeatured($limit);
    }
}
