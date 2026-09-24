<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Domain\Catalog\Bundle;
use App\Domain\Catalog\Port\BundleCatalog;

final readonly class ShowBundle
{
    public function __construct(private BundleCatalog $bundles) {}

    /**
     * @throws BundleNotFoundException
     */
    public function handle(string $slug): Bundle
    {
        return $this->bundles->findBySlug($slug)
            ?? BundleNotFoundException::forSlug($slug);
    }
}
