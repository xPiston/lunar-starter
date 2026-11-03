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
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lunar\Facades\AttributeManifest;
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
    /**
     * What a product card needs, loaded up front.
     *
     * Not decoration: `benchmarks/browse.js` and a query log put numbers on
     * what happens without it. A twelve-product collection page ran **106
     * queries** - 24 for the variants' prices, 24 more for those prices'
     * currencies, 24 re-reading the variant each price belongs to, 12 for the
     * thumbnails and 12 for the slugs - because `ProductMapper::toSummary()`
     * reads `defaultUrl`, `thumbnail` and each variant's price, and none of
     * the three was in the eager-load list.
     *
     * Every relation here is one the mapper actually reads, and nothing it
     * does not: `media` (the full gallery) and `variants.values` (the option
     * labels) are only used on a product's own page, so they belong to
     * self::DETAIL and would be a hundred rows thrown away on a listing.
     *
     * `prices.priceable` looks circular - it is the variant the price hangs
     * off, which is already loaded - and it is not optional. Lunar casts the
     * `price` column through `Lunar\Base\Casts\Price`, which reads
     * `$model->priceable->unit_quantity` to build its Price object, so merely
     * *reading* a price loads its variant back one row at a time. Asking for
     * it here is one query instead of one per variant.
     *
     * `tests/Feature/Storefront/QueryBudgetTest.php` is what keeps this list
     * honest: it fails if a listing's query count grows with the number of
     * products on the page.
     */
    private const SUMMARY = ['defaultUrl', 'thumbnail', 'variants.prices.currency', 'variants.prices.priceable'];

    /** The same, plus what only a single product's page renders. */
    private const DETAIL = ['defaultUrl', 'thumbnail', 'media', 'variants.values', 'variants.prices.currency', 'variants.prices.priceable'];

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
            ->with(self::SUMMARY)
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
        //
        // The explicit select is not cosmetic. Taking the builder out of the
        // relation loses the `lunar_products.*` select a belongs-to-many adds
        // when it runs itself, so the query becomes `select *` over the join
        // and the pivot's own `id` column overwrites the product's. Every
        // product then carries its pivot row's id, which silently poisons
        // anything read afterwards - the thumbnail and url relations load for
        // the wrong key, and a product whose pivot id happens not to match
        // another product's id comes back with no image and a dead link.
        /** @var Builder<LunarProduct> $products */
        $products = $collection->products()->getQuery()->select('lunar_products.*');

        return $this->paginate($products, $query);
    }

    /**
     * Two paths, chosen by whether there is an index to ask.
     *
     * With a real engine configured (`SCOUT_DRIVER=meilisearch`, `algolia`,
     * `typesense`...), Lunar's `Searchable` trait does the work and a store
     * gets relevance ranking for free - that part has not changed, and it is
     * still why search goes through Scout rather than being hand-rolled.
     *
     * Without one, it is a database query instead of Scout's `collection`
     * engine. That engine has no index and matches in PHP: it `get()`s the
     * whole products table, hydrates every model with the relations the
     * indexer reads (variants, thumbnail, product type, brand), builds a
     * searchable array per product and lowercases every value looking for a
     * substring. `benchmarks/search.js` measured what that costs - 273 ms at
     * 7 products, 1.32 s at 250, 4.33 s at 1 000, 9.22 s at 2 007 and
     * **78 seconds** at 10 007, peaking 142 MB, while a collection listing of
     * the same catalogue stayed flat at ~460 ms. A term matching nothing cost
     * the same as one matching everything, because it had to look at all of
     * it to find out. At that size the page was not slow, it was dead.
     *
     * The query below asks Postgres the same question. It is not an index
     * either: an unanchored LIKE cannot use a b-tree, so this is still a
     * scan - but a scan of one column in the database rather than a scan of
     * hydrated Eloquent models in PHP, which is three orders of magnitude of
     * difference and enough to make the default usable. A catalogue large
     * enough to feel *this* is the point at which a real engine stops being
     * optional, and switching to one is still only a config change.
     *
     * Either way the ids go through `paginate()`, which re-applies the same
     * published/channel/eager-loading rules as every other listing here -
     * search never bypasses product visibility.
     */
    public function search(string $term, ProductQuery $query): ProductListing
    {
        // One shape, two sources for the ids. Asking for the ids first and
        // paging over them separately is not just symmetry with the Scout
        // path: `paginate()` counts the whole result before slicing it, so
        // handing it the matching query directly would run the scan twice -
        // measured at 568 ms for the page and 652 ms for the count, on the
        // same 10 000 products. The list is bounded by the catalogue rather
        // than by the page, which is the same bargain the indexed path has
        // always made.
        $ids = $this->hasSearchIndex()
            ? LunarProduct::search($term)->keys()
            : $this->matching($term)->pluck('id');

        if ($ids->isEmpty()) {
            return ProductListing::empty($query);
        }

        return $this->paginate(LunarProduct::query()->whereIn('id', $ids), $query);
    }

    /**
     * Whether the configured Scout driver actually holds an index.
     *
     * Asks the engine rather than reading `scout.driver`, so a project that
     * registers its own engine is assumed to have an index - which is the
     * safe default, since the alternative would silently stop using it. The
     * three listed here are the ones that have nothing to ask: `collection`
     * matches in PHP, `null` matches nothing, and `database` searches real
     * columns, which Lunar's attributes are not - they live inside the
     * `attribute_data` JSON document.
     */
    private function hasSearchIndex(): bool
    {
        $engine = app(EngineManager::class)->engine();

        return ! $engine instanceof CollectionEngine
            && ! $engine instanceof DatabaseEngine
            && ! $engine instanceof NullEngine;
    }

    /**
     * Products whose searchable attributes, SKUs or brand contain the term.
     *
     * The attributes are the ones flagged `searchable` in the `attributes`
     * table - the same list the Scout indexer reads, so turning an attribute
     * off in the admin takes it out of search here too, with or without an
     * engine. SKUs are matched as well, because a customer holding a
     * delivery note types the code on it, and so is the brand.
     *
     * What is deliberately NOT matched is everything else the Scout indexer
     * puts in its document. That indexer also writes `status`, `product_type`
     * and `created_at`, and the collection engine matched a substring against
     * every value in it - so on the demo catalogue a shopper searching
     * "published" got all seven products, and "stock" got all seven again,
     * that being the name of the product type. Measured, not supposed:
     * `Product::search('published')->keys()` returned 7 of 7. Internal
     * metadata has no business being a search term; a brand is the one thing
     * in that document a customer would actually type.
     *
     * `whereLike` rather than a raw `ilike`: it compiles to `ilike` on
     * Postgres and a case-insensitive `like` on MySQL, so this stays the one
     * place that does not have to know which database is underneath. The
     * handles go into the JSON path by interpolation because a path is not a
     * bindable value; they come from the `attributes` table, and the query
     * grammar quotes each path segment, so an exotic handle is escaped rather
     * than executed.
     *
     * @return Builder<LunarProduct>
     */
    private function matching(string $term): Builder
    {
        // `%` and `_` are wildcards to SQL and ordinary characters to someone
        // typing in a search box. Unescaped, a search for "%" would return
        // the entire catalogue and "a_c" would match "abc" - neither of which
        // is what the shopper asked, and the first is a free full scan for
        // anyone who wants one.
        $contains = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return LunarProduct::query()->where(function (Builder $products) use ($contains): void {
            foreach ($this->searchableHandles() as $handle) {
                $products->orWhereLike("attribute_data->{$handle}->value", $contains);
            }

            $products->orWhereHas('variants', fn (Builder $variants) => $variants->whereLike('sku', $contains));
            $products->orWhereHas('brand', fn (Builder $brands) => $brands->whereLike('name', $contains));
        });
    }

    /**
     * @return array<int, string>
     */
    private function searchableHandles(): array
    {
        /** @var array<int, string> $handles */
        $handles = AttributeManifest::getSearchableAttributes(LunarProduct::morphName())
            ->pluck('handle')
            ->all();

        return $handles;
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
            ->with(self::SUMMARY)
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
            ProductSort::PriceLowToHigh => $this->orderByCheapest($products, 'asc'),
            ProductSort::PriceHighToLow => $this->orderByCheapest($products, 'desc'),
        };

        // Every sort ends on the id so a product never changes page between
        // two requests because two rows compared equal.
        $products->orderBy('lunar_products.id');
    }

    /**
     * Orders a listing by what its products cost.
     *
     * Base price, not the one `Pricing::for()` would resolve: that one
     * depends on the customer group and quantity tier of whoever is looking,
     * which cannot be expressed as a column to sort a page by. With no group
     * pricing configured - the default here - the two agree exactly. With
     * group pricing, a listing sorted by price can disagree with the "from"
     * price shown on a card, and the answer at that point is an index
     * (Meilisearch, Algolia) holding a price per group, not a heavier join.
     *
     * Joined once, not asked per row. This used to be a correlated subquery -
     * `min(price)` over the prices of the variants of `lunar_products.id` -
     * which the database has to run again for every candidate product, and
     * `benchmarks/browse.js` found the bill: on a 2 000-product collection
     * `?sort=price_asc` took **5.35 s**, the slowest page in the whole suite
     * once search had been dealt with. Aggregating the price table once and
     * joining the result is the same answer in one pass.
     *
     * The explicit select is not cosmetic, for the same reason it is not in
     * `listByCollection()`: a join without it makes the query `select *`
     * across both tables, and the derived table's `product_id` would
     * overwrite the product's own id.
     *
     * @param  Builder<LunarProduct>  $products
     */
    private function orderByCheapest(Builder $products, string $direction): void
    {
        $products
            ->select('lunar_products.*')
            ->leftJoinSub($this->cheapestPrices(), 'cheapest', 'cheapest.product_id', '=', 'lunar_products.id')
            ->orderBy('cheapest.amount', $direction);
    }

    /**
     * The cheapest base price per product, as a derived table to join.
     *
     * @return Builder<Model>
     */
    private function cheapestPrices(): Builder
    {
        /** @var Builder<Model> $prices */
        $prices = LunarPrice::query()
            ->selectRaw('lunar_product_variants.product_id as product_id, min(lunar_prices.price) as amount')
            ->join('lunar_product_variants', 'lunar_product_variants.id', '=', 'lunar_prices.priceable_id')
            ->whereNull('lunar_product_variants.deleted_at')
            ->where('lunar_prices.priceable_type', (new LunarProductVariant)->getMorphClass())
            ->groupBy('lunar_product_variants.product_id');

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
     * Columns are qualified because `cheapestPrices()` joins the variants
     * table, which has a `min_quantity` of its own - unqualified, the
     * database cannot tell which one this means and refuses the query.
     *
     * @param  Builder<Model>  $prices
     * @return Builder<Model>
     */
    private function basePrices(Builder $prices): Builder
    {
        /** @var LunarCurrency $currency */
        $currency = StorefrontSession::getCurrency();

        return $prices
            ->where('lunar_prices.currency_id', $currency->id)
            ->whereNull('lunar_prices.customer_group_id')
            ->where('lunar_prices.min_quantity', '<=', 1);
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
            ->with(self::DETAIL)
            ->status('published')
            ->channel(StorefrontSession::getChannel())
            ->first();

        return $product ? $this->mapper->toDomain($product) : null;
    }

    /**
     * Every published product's slug, for the sitemap.
     *
     * The published set is a subquery, not a list of ids fetched and sent
     * back. It used to be `pluck('id')` followed by `whereIn` - which on a
     * 10 000-product catalogue means ten thousand integers travelling to PHP
     * and then ten thousand bind parameters travelling back, and
     * `benchmarks/browse.js` found `/sitemap.xml` taking **1.08 s** because
     * of it. The XML is not the expensive part: generating the 10 022 URLs
     * costs 133 ms and serialising them 8 ms, measured.
     *
     * @return string[]
     */
    public function listPublishedSlugs(): array
    {
        /** @var string[] $slugs */
        $slugs = Url::query()
            ->where('element_type', (new LunarProduct)->getMorphClass())
            ->where('default', true)
            ->whereIn('element_id', LunarProduct::query()
                ->status('published')
                ->channel(StorefrontSession::getChannel())
                ->select('lunar_products.id'))
            ->pluck('slug')
            ->all();

        return $slugs;
    }

    public function listCollections(): array
    {
        // `defaultUrl` eager-loaded because the navbar asks for this list on
        // every storefront page (see HandleInertiaRequests): without it each
        // collection fetches its own slug, so the shared navigation costs a
        // query per entry on every single request.
        $collections = LunarCollection::query()
            ->channel(StorefrontSession::getChannel())
            ->with('defaultUrl')
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
