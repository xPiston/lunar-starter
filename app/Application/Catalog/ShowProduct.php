<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\Product;

final readonly class ShowProduct
{
    public function __construct(private ProductCatalog $catalog) {}

    public function handle(string $slug): Product
    {
        return $this->catalog->findBySlug($slug) ?? throw ProductNotFoundException::forSlug($slug);
    }
}
