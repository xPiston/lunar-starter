<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductQuery;

final readonly class ListProductsByCollection
{
    public function __construct(private ProductCatalog $catalog) {}

    public function handle(string $collectionSlug, ProductQuery $query = new ProductQuery): ProductListing
    {
        return $this->catalog->listByCollection($collectionSlug, $query);
    }
}
