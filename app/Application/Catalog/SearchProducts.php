<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductQuery;

final readonly class SearchProducts
{
    public function __construct(private ProductCatalog $catalog) {}

    public function handle(string $term, ProductQuery $query = new ProductQuery): ProductListing
    {
        return $this->catalog->search($term, $query);
    }
}
