<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\Port\ProductCatalog;

final readonly class ListCollections
{
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * @return CollectionSummary[]
     */
    public function handle(): array
    {
        return $this->catalog->listCollections();
    }
}
