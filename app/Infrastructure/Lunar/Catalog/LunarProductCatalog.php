<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Catalog;

use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency as LunarCurrency;
use Lunar\Models\Price as LunarPrice;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\ProductVariant as LunarProductVariant;
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

    public function listByCollection(string $collectionSlug, ProductQuery $query): ProductListing
    {
        $collection = $this->findCollectionBySlug($collectionSlug);

        if (! $collection) {
            return ProductListing::empty($query);
        }

        // The relation's underlying builder, already constrained to this
        // collection. Typed locally because Lunar resolves its models at
        // runtime, so the relation's generic is a bare Eloquent Model here.
        /** @var Builder<LunarProduct> $products */
        $products = $collection->products()->getQuery();

        return $this->paginate($products, $query);
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
    public function search(string $term, ProductQuery $query): ProductListing
    {
        $ids = LunarProduct::search($term)->keys();

        if ($ids->isEmpty()) {
            return ProductListing::empty($query);
        }

        return $this->paginate(LunarProduct::query()->whereIn('id', $ids), $query);
    }

    /**
     * Applies visibility, filters, ordering and the page window to a product
     * query, and counts the whole set before slicing it.
     *
     * @param  Builder<LunarProduct>  $products
     */
    private function paginate(Builder $products, ProductQuery $query): ProductListing
    {
        $products
            ->status('published')
            ->channel(StorefrontSession::getChannel());

        $this->applyFilters($products, $query);

        // Counted before the ordering and the window: a page is only
        // meaningful against the size of the whole result.
        $total = (clone $products)->distinct()->count('lunar_products.id');

        $this->applySort($products, $query);

        $page = $products
            ->with(['variants.values', 'media'])
            ->offset($query->offset())
            ->limit($query->perPage)
            ->get();

        return new ProductListing(
            items: $page->map($this->mapper->toSummary(...))->all(),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
        );
    }

    /**
     * @param  Builder<LunarProduct>  $products
     */
    private function applyFilters(Builder $products, ProductQuery $query): void
    {
        if ($query->inStockOnly) {
            // "Available", not "stock > 0": a variant marked purchasable
            // `always` has no stock to speak of and is still buyable, which is
            // exactly what the cart's own check allows.
            $products->whereHas('variants', function (Builder $variants): void {
                $variants->where('purchasable', 'always')->orWhere('stock', '>', 0);
            });
        }

        if ($query->minPrice !== null) {
            $products->whereHas('variants.prices', fn (Builder $prices) => $this->basePrices($prices)->where('price', '>=', $query->minPrice));
        }

        if ($query->maxPrice !== null) {
            $products->whereHas('variants.prices', fn (Builder $prices) => $this->basePrices($prices)->where('price', '<=', $query->maxPrice));
        }
    }

    /**
     * @param  Builder<LunarProduct>  $products
     */
    private function applySort(Builder $products, ProductQuery $query): void
    {
        match ($query->sort) {
            ProductSort::Newest => $products->latest('lunar_products.created_at'),
            // A product's name is an attribute in a JSON column, not a
            // column, so alphabetical order reads it out of the document.
            // This assumes the `name` attribute is plain Text, which is what
            // Lunar ships and what this template seeds; switch it to
            // TranslatedText and the order becomes the raw JSON's, which is
            // wrong but harmless - the fix at that point is a generated
            // column, or the search index that faceted sorting wants anyway.
            ProductSort::NameAToZ => $products->orderByRaw("lunar_products.attribute_data->'name'->>'value' asc"),
            ProductSort::PriceLowToHigh => $products->orderBy($this->cheapestPrice(), 'asc'),
            ProductSort::PriceHighToLow => $products->orderBy($this->cheapestPrice(), 'desc'),
        };

        // Every sort ends on the id so a product never changes page between
        // two requests because two rows compared equal.
        $products->orderBy('lunar_products.id');
    }

    /**
     * The cheapest base price of a product, as a subquery the database can
     * order by.
     *
     * Base price, not the one `Pricing::for()` would resolve: that one
     * depends on the customer group and quantity tier of whoever is looking,
     * which cannot be expressed as a column to sort a page by. With no group
     * pricing configured - the default here - the two agree exactly. With
     * group pricing, a listing sorted by price can disagree with the "from"
     * price shown on a card, and the answer at that point is an index
     * (Meilisearch, Algolia) holding a price per group, not a heavier join.
     *
     * @return Builder<Model>
     */
    private function cheapestPrice(): Builder
    {
        /** @var Builder<Model> $prices */
        $prices = LunarPrice::query()
            ->selectRaw('min(price)')
            ->where('priceable_type', (new LunarProductVariant)->getMorphClass())
            ->whereIn('priceable_id', LunarProductVariant::query()
                ->select('id')
                ->whereColumn('product_id', 'lunar_products.id'));

        return $this->basePrices($prices);
    }

    /**
     * The list price everyone sees: current currency, single unit, no
     * customer group.
     *
     * Typed against a bare Model rather than LunarPrice: it is handed both a
     * price query built here and the one Eloquent passes to a `whereHas`
     * closure, which Lunar's runtime model resolution leaves ungeneric.
     *
     * @param  Builder<Model>  $prices
     * @return Builder<Model>
     */
    private function basePrices(Builder $prices): Builder
    {
        /** @var LunarCurrency $currency */
        $currency = StorefrontSession::getCurrency();

        return $prices
            ->where('currency_id', $currency->id)
            ->whereNull('customer_group_id')
            ->where('min_quantity', '<=', 1);
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

    public function listPublishedSlugs(): array
    {
        $productIds = LunarProduct::query()
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->pluck('id');

        return Url::query()
            ->where('element_type', (new LunarProduct)->getMorphClass())
            ->whereIn('element_id', $productIds)
            ->where('default', true)
            ->pluck('slug')
            ->map(static fn (string $slug): string => $slug)
            ->all();
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
