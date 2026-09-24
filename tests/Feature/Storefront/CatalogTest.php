<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the Catalog context, end-to-end: route -> use case
 * -> port -> Lunar adapter -> Inertia page. Against a real PostgreSQL
 * database (Lunar doesn't support SQLite), as RefreshDatabase expects.
 */
final class CatalogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_home_page_lists_published_products(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/home')
                ->has('products', 1)
                ->where('products.0.name', $product->translateAttribute('name'))
                ->where('products.0.price_from.formatted', '$24.99')
            );
    }

    public function test_product_page_shows_variants_and_price(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $slug = (string) $product->defaultUrl->slug;

        $this->get(route('products.show', $slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/product')
                ->where('product.slug', $slug)
                ->has('product.variants', 1)
                ->where('product.variants.0.price.formatted', '$24.99')
            );
    }

    public function test_product_page_exposes_available_stock(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499, stock: 3);
        $slug = (string) $product->defaultUrl->slug;

        $this->get(route('products.show', $slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.variants.0.available_stock', 3)
            );
    }

    public function test_unknown_product_slug_returns_404(): void
    {
        $this->get(route('products.show', 'does-not-exist'))->assertNotFound();
    }

    public function test_the_navbar_collections_are_shared_on_storefront_pages_only(): void
    {
        Collection::create([
            'collection_group_id' => CollectionGroup::create(['name' => 'Main', 'handle' => 'main'])->id,
            'attribute_data' => collect(['name' => new Text('Navbar Collection')]),
        ]);

        // Any storefront page, not just the home page: the navbar lives in
        // the shared layout, so every one of them needs the links.
        $this->get(route('cart.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navCollections.0.name', 'Navbar Collection')
            );

        // Pages outside the storefront route group have no navbar, so they
        // must not pay for the query behind it - the point of the whole
        // MarkStorefrontRequest indirection, hence asserting on the queries
        // themselves rather than just on the (empty) prop.
        $collectionsTable = (new Collection)->getTable();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('navCollections', []));

        $this->assertEmpty(
            array_filter($queries, static fn (string $sql): bool => str_contains($sql, $collectionsTable)),
            'The dashboard should not query collections: it has no navbar.',
        );
    }

    public function test_collection_page_only_lists_its_own_products(): void
    {
        $inCollection = $this->createDemoProduct('In The Collection', 1999);
        $this->createDemoProduct('Outside The Collection', 999);

        $collection = Collection::create([
            'collection_group_id' => CollectionGroup::create(['name' => 'Main', 'handle' => 'main'])->id,
            'attribute_data' => collect(['name' => new Text('My Collection')]),
        ]);
        $inCollection->collections()->attach($collection->id);
        $collectionSlug = (string) $collection->defaultUrl->slug;

        $this->get(route('collections.show', $collectionSlug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/collection')
                ->has('listing.items', 1)
                ->where('listing.items.0.name', $inCollection->translateAttribute('name'))
            );
    }
}
