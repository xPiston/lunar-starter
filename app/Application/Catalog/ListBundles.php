<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Bundle;
use App\Domain\Catalog\Port\BundleCatalog;

final readonly class ListBundles
{
    public function __construct(private BundleCatalog $bundles) {}

    /**
     * @return Bundle[]
     */
    public function handle(): array
    {
        return $this->bundles->listActive();
    }
}
