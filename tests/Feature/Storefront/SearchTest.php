<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for product search: route -> use case -> port -> Lunar's
 * Scout-backed `Searchable` trait -> Inertia page. Uses the `collection`
 * Scout driver (this template's zero-infra default, see README) - no
 * external search service to run for these to pass.
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
}
