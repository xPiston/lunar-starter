<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Product;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for paging, ordering and filtering a listing.
 *
 * The first one is the reason this exists: the catalogue used to be cut off
 * at a hard limit of 24, so every product past the second dozen was
 * unreachable from the storefront and from a crawler.
 */
final class ProductListingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_collection_bigger_than_one_page_is_fully_reachable(): void
    {
        $slug = $this->collectionOf(count: 30);

        $this->get(route('collections.show', $slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 24)
                ->where('listing.total', 30)
                ->where('listing.page', 1)
                ->where('listing.last_page', 2)
            );

        $this->get(route('collections.show', $slug).'?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 6)
                ->where('listing.page', 2)
            );
    }

    /**
     * Pages must partition the catalogue: thirty products across two pages,
     * each appearing exactly once.
     *
     * Note what this does NOT prove. The adapter ends every sort on the id so
     * that rows comparing equal keep a fixed order, but Postgres returns
     * these thirty tied rows consistently at this size whether or not that
     * tie-breaker is there - removing it leaves the test green. It stays
     * because the database guarantees nothing about ties, not because a test
     * caught it.
     */
    public function test_no_product_is_repeated_or_skipped_between_pages(): void
    {
        $slug = $this->collectionOf(count: 30);

        // Every product published at the same instant, which is what a bulk
        // import produces. The default order is "newest first", so without a
        // tie-breaker the database is free to return these thirty rows in a
        // different order on each query - and a product then sits on both
        // pages, or on neither.
        DB::table('lunar_products')->update(['created_at' => now()]);

        $first = $this->idsOn($slug, page: 1);
        $second = $this->idsOn($slug, page: 2);

        $this->assertCount(30, array_unique([...$first, ...$second]));
    }

    public function test_products_can_be_ordered_by_price(): void
    {
        $slug = $this->collectionOf(count: 0);
        $this->addToCollection($slug, $this->createDemoProduct('Expensive', 9900));
        $this->addToCollection($slug, $this->createDemoProduct('Cheap', 1000));
        $this->addToCollection($slug, $this->createDemoProduct('Middle', 5000));

        $this->get(route('collections.show', $slug).'?sort=price_asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.items.0.name', 'Cheap')
                ->where('listing.items.2.name', 'Expensive')
            );

        $this->get(route('collections.show', $slug).'?sort=price_desc')
            ->assertInertia(fn (Assert $page) => $page->where('listing.items.0.name', 'Expensive'));
    }

    public function test_products_can_be_ordered_alphabetically(): void
    {
        $slug = $this->collectionOf(count: 0);
        $this->addToCollection($slug, $this->createDemoProduct('Zebra Tee', 1000));
        $this->addToCollection($slug, $this->createDemoProduct('Apple Tee', 2000));

        $this->get(route('collections.show', $slug).'?sort=name')
            ->assertInertia(fn (Assert $page) => $page->where('listing.items.0.name', 'Apple Tee'));
    }

    public function test_a_price_range_narrows_the_listing(): void
    {
        $slug = $this->collectionOf(count: 0);
        $this->addToCollection($slug, $this->createDemoProduct('Cheap', 1000));
        $this->addToCollection($slug, $this->createDemoProduct('Expensive', 9900));

        // Entered in whole units by the visitor, held in cents by the domain.
        $this->get(route('collections.show', $slug).'?min_price=50')
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 1)
                ->where('listing.items.0.name', 'Expensive')
                ->where('filters.min_price', 5000)
            );

        $this->get(route('collections.show', $slug).'?max_price=50')
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 1)
                ->where('listing.items.0.name', 'Cheap')
            );
    }

    public function test_out_of_stock_products_can_be_hidden(): void
    {
        $slug = $this->collectionOf(count: 0);
        $this->addToCollection($slug, $this->createDemoProduct('Available', 1000, stock: 5));
        $this->addToCollection($slug, $this->createDemoProduct('Sold out', 1000, stock: 0));

        $this->get(route('collections.show', $slug))
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 2));

        $this->get(route('collections.show', $slug).'?in_stock=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 1)
                ->where('listing.items.0.name', 'Available')
            );
    }

    /**
     * A page of a catalogue is worth indexing - it holds products nothing
     * else links to. Every combination of filters is the same catalogue
     * sliced differently, and letting a crawler enumerate them wastes its
     * budget on duplicates.
     */
    public function test_paging_stays_indexable_but_filtering_does_not(): void
    {
        $slug = $this->collectionOf(count: 30);

        $plain = $this->get(route('collections.show', $slug).'?page=2');
        $plain->assertOk();
        $this->assertStringNotContainsString('name="robots"', $plain->getContent() ?: '');

        $this->get(route('collections.show', $slug).'?sort=price_asc')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_an_unknown_sort_is_rejected_rather_than_guessed(): void
    {
        $slug = $this->collectionOf(count: 1);

        // The value reaches an `order by`, so it is validated against the
        // enum rather than passed through.
        $this->get(route('collections.show', $slug).'?sort=id;drop+table')
            ->assertSessionHasErrors('sort');
    }

    public function test_search_results_are_paginated_too(): void
    {
        $this->collectionOf(count: 0);

        for ($i = 0; $i < 26; $i++) {
            $this->createDemoProduct("Paginated Tee {$i}", 1000 + $i);
        }

        $this->get(route('search', ['q' => 'Paginated']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('listing.items', 24)
                ->where('listing.total', 26)
            );

        $this->get(route('search', ['q' => 'Paginated', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('listing.items', 2));
    }

    /**
     * @return int[]
     */
    private function idsOn(string $slug, int $page): array
    {
        $response = $this->get(route('collections.show', $slug)."?page={$page}");
        $items = $response->viewData('page')['props']['listing']['items'];

        return array_column($items, 'id');
    }

    /**
     * Creates a collection holding `$count` products, and returns its slug.
     * Lunar generates the url itself from the name attribute.
     */
    private function collectionOf(int $count): string
    {
        $collection = Collection::create([
            'collection_group_id' => CollectionGroup::create(['name' => 'Main', 'handle' => 'main'])->id,
            'attribute_data' => collect(['name' => new Text('Bulk Collection')]),
        ]);

        for ($i = 0; $i < $count; $i++) {
            $collection->products()->attach($this->createDemoProduct("Bulk Tee {$i}", 1000 + $i)->id);
        }

        return (string) $collection->defaultUrl->slug;
    }

    private function addToCollection(string $slug, Product $product): void
    {
        Collection::whereHas('urls', fn ($urls) => $urls->where('slug', $slug))
            ->firstOrFail()
            ->products()
            ->attach($product->id);
    }
}
