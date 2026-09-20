<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Catalog;

use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\Product;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\Url;

/**
 * Outbound adapter: Lunar implementation of the ProductCatalog port.
 *
 * This is the ONLY place (along with ProductMapper) that imports Lunar
 * classes for the Catalog context. The domain and application layers know
 * nothing about it.
 */
final class LunarProductCatalog implements ProductCatalog
{
    public function __construct(private readonly ProductMapper $mapper) {}

    public function listFeatured(int $limit = 8): array
    {
        // Lunar has no native notion of a "featured product": we just take
        // the most recently published ones. For real featuring, create a
        // dedicated ("featured") Collection and switch this method to
        // listByCollection('featured', $limit) - without touching the port
        // or the use cases.
        $products = LunarProduct::query()
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->with(['variants.values', 'media'])
            ->latest()
            ->limit($limit)
            ->get();

        return $products->map($this->mapper->toSummary(...))->all();
    }

    public function listByCollection(string $collectionSlug, int $limit = 24): array
    {
        $collection = $this->findCollectionBySlug($collectionSlug);

        if (! $collection) {
            return [];
        }

        $products = $collection->products()
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->with(['variants.values', 'media'])
            ->limit($limit)
            ->get();

        return $products->map($this->mapper->toSummary(...))->all();
    }

    /**
     * Uses Lunar's built-in `Searchable` trait (Laravel Scout under the
     * hood) rather than a hand-rolled `LIKE` query: a real store gets a
     * relevance-ranked Meilisearch/Algolia index for free by changing
     * `SCOUT_DRIVER` and running `artisan scout:import` - nothing here
     * changes. Out of the box (`SCOUT_DRIVER=collection`, Scout's own
     * default, no service to run) it's a plain in-memory substring match
     * across every searchable attribute, which is fine for a small catalog
     * but not something to leave running against a large one.
     *
     * Scout returns matching ids first (`->keys()`), then a normal query
     * re-applies the same published/channel/eager-loading rules as every
     * other listing here - search never bypasses product visibility.
     */
    public function search(string $query, int $limit = 24): array
    {
        $ids = LunarProduct::search($query)->keys();

        if ($ids->isEmpty()) {
            return [];
        }

        $products = LunarProduct::query()
            ->whereIn('id', $ids)
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->with(['variants.values', 'media'])
            ->limit($limit)
            ->get();

        return $products->map($this->mapper->toSummary(...))->all();
    }

    public function findBySlug(string $slug): ?Product
    {
        $url = Url::where('slug', $slug)
            ->where('element_type', (new LunarProduct)->getMorphClass())
            ->first();

        if (! $url) {
            return null;
        }

        /** @var LunarProduct|null $product */
        $product = $url->element()
            ->with(['variants.values', 'media'])
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->first();

        return $product ? $this->mapper->toDomain($product) : null;
    }

    public function listCollections(): array
    {
        $collections = LunarCollection::query()
            ->channel(StorefrontSession::getChannel())
            ->get();

        return $collections
            ->map(fn (LunarCollection $collection): CollectionSummary => new CollectionSummary(
                id: $collection->id,
                name: $collection->translateAttribute('name'),
                slug: (string) $collection->defaultUrl?->slug,
            ))
            ->all();
    }

    private function findCollectionBySlug(string $slug): ?LunarCollection
    {
        $url = Url::where('slug', $slug)
            ->where('element_type', (new LunarCollection)->getMorphClass())
            ->first();

        return $url?->element;
    }
}
