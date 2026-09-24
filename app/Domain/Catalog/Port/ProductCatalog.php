<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Port;

use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductSummary;

/**
 * OUTBOUND port of the Catalog context.
 *
 * Expressed in the domain's vocabulary (Product, ProductSummary), never in
 * a technology's: the domain has no idea Lunar (or any other e-commerce
 * engine) sits behind it. This is the ONLY contract
 * `App\Infrastructure\Lunar\Catalog\LunarProductCatalog` must honor so that
 * the rest of the application (use cases, controllers, pages) keeps working
 * unmodified if Lunar is ever replaced.
 */
interface ProductCatalog
{
    /**
     * @return ProductSummary[]
     */
    public function listFeatured(int $limit = 8): array;

    /**
     * One page of a collection, ordered and narrowed as asked.
     *
     * Returns a listing rather than an array: a caller that only gets the
     * rows cannot tell whether there are more, which is how a catalogue ends
     * up with everything past the first two dozen products unreachable.
     */
    public function listByCollection(string $collectionSlug, ProductQuery $query): ProductListing;

    public function search(string $term, ProductQuery $query): ProductListing;

    public function findBySlug(string $slug): ?Product;

    /**
     * Slugs of every published product, for callers that need the shape of the
     * catalogue rather than its contents (a sitemap, a cache warmer).
     *
     * Deliberately not `listFeatured()` with a large limit: that builds a full
     * ProductSummary per row, and resolving a price per variant across the
     * whole catalogue is far more work than the caller asked for.
     *
     * @return string[]
     */
    public function listPublishedSlugs(): array;

    /**
     * @return CollectionSummary[]
     */
    public function listCollections(): array;
}
