<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Base\AttributeManifestInterface;
use Lunar\Models\Attribute;
use Lunar\Models\Brand;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for product search: route -> use case -> port -> Inertia
 * page. Run with `SCOUT_DRIVER=collection`, this template's zero-infra
 * default (see README), which means no external search service has to run
 * for these to pass - and, since there is no index behind that driver, it is
 * `LunarProductCatalog::matching()` answering rather than Scout. Configure a
 * real engine and the same assertions have to hold through it.
 */
final class SearchTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_searching_finds_a_product_by_name(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);
        $this->createDemoProduct('Embroidered Cap', 1499);

        $this->get(route('search', ['q' => 'Navy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/search')
                ->where('query', 'Navy')
                ->has('listing.items', 1)
                ->where('listing.items.0.name', 'Navy Plain T-Shirt')
            );
    }

    public function test_an_empty_query_returns_no_results_without_erroring(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);

        $this->get(route('search'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/search')
                ->where('query', '')
                ->has('listing.items', 0)
            );
    }

    public function test_a_query_matching_nothing_returns_an_empty_result_set(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);

        $this->get(route('search', ['q' => 'nonexistent-widget']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 0));
    }

    public function test_search_only_returns_published_products(): void
    {
        $product = $this->createDemoProduct('Navy Plain T-Shirt', 1999);
        $product->update(['status' => 'draft']);

        $this->get(route('search', ['q' => 'Navy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 0));
    }

    public function test_the_case_typed_does_not_matter(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);

        $this->get(route('search', ['q' => 'nAvY']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 1));
    }

    public function test_every_searchable_attribute_is_searched_not_just_the_name(): void
    {
        // createDemoProduct writes "Description for <name>", so this term is
        // only ever in the description.
        $this->createDemoProduct('Embroidered Cap', 1499);

        $this->get(route('search', ['q' => 'Description for Embroidered']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 1));
    }

    public function test_an_attribute_turned_off_in_the_admin_leaves_search(): void
    {
        $this->createDemoProduct('Embroidered Cap', 1499);

        Attribute::where('attribute_type', 'product')->where('handle', 'description')->update(['searchable' => false]);
        // The manifest caches the list per request; a fresh request is what a
        // shop owner would make after saving the attribute anyway.
        $this->app->forgetInstance(AttributeManifestInterface::class);

        $this->get(route('search', ['q' => 'Description for Embroidered']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 0));

        $this->get(route('search', ['q' => 'Embroidered']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 1));
    }

    public function test_a_sku_is_a_search_term(): void
    {
        $product = $this->createDemoProduct('Embroidered Cap', 1499);

        $this->get(route('search', ['q' => $this->firstVariant($product)->sku]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 1));
    }

    public function test_a_brand_is_a_search_term(): void
    {
        $product = $this->createDemoProduct('Embroidered Cap', 1499);
        $product->update(['brand_id' => Brand::create(['name' => 'Northwind'])->id]);

        $this->get(route('search', ['q' => 'northwind']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 1));
    }

    /**
     * Scout's indexer also writes `status`, `product_type` and `created_at`
     * into its document, and the collection engine matched a substring
     * against every value in it: `Product::search('published')` returned all
     * seven demo products, and so did `search('stock')`, that being the name
     * of the product type. A shopper should not be able to select the whole
     * catalogue by typing a word out of the admin panel.
     */
    public function test_internal_metadata_is_not_a_search_term(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);
        $this->createDemoProduct('Embroidered Cap', 1499);

        foreach (['published', 'Stock'] as $term) {
            $this->get(route('search', ['q' => $term]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('listing.items', 0));
        }
    }

    /**
     * `%` and `_` mean something to SQL and nothing to a shopper.
     *
     * This is a guard on the new implementation rather than a fix: Scout's
     * collection engine compared substrings in PHP, where both characters
     * were already literal, so the behaviour asserted here is the behaviour
     * that was. Without the escaping in `matching()` a database query would
     * quietly change it - a search for "%" handing back the whole catalogue
     * (a free full scan for anyone who wants one) and "N_vy" finding "Navy".
     */
    public function test_sql_wildcards_are_matched_literally(): void
    {
        $this->createDemoProduct('Navy Plain T-Shirt', 1999);
        $this->createDemoProduct('100% Organic Cotton Tee', 2499);

        $this->get(route('search', ['q' => '%']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 1)
                ->where('listing.items.0.name', '100% Organic Cotton Tee')
            );

        // "N_vy" must not find "Navy".
        $this->get(route('search', ['q' => 'N_vy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 0));
    }
}
