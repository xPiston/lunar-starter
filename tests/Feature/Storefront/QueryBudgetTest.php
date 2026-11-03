<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * Tests for a property no functional test can see: that the work a page does
 * stays the same size as the catalogue grows.
 *
 * Both of these were real. A twelve-product collection page ran 106 queries,
 * because `ProductMapper::toSummary()` reads `defaultUrl`, `thumbnail` and
 * each variant's price and none of the three was eager-loaded. The sitemap
 * fetched every published product id into PHP and sent them all back as bind
 * parameters, which took 1.08 s on ten thousand products. Neither showed up
 * as a failure anywhere - the pages were correct, and on seven demo products
 * they were fast too.
 *
 * So these assert budgets rather than durations. A duration on a shared CI
 * runner measures the runner; a query count and a bind-parameter count are
 * the same numbers on any machine, and they are what actually changed.
 *
 * The numbers are headroom, not targets: they are set well above what the
 * code does today, so ordinary work does not trip them and a regression of
 * this shape - which multiplies rather than adds - does.
 */
final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    /**
     * Counts the queries one request makes, warming up first.
     *
     * The warm-up matters: Lunar memoises its channel, currency and customer
     * group, and the attribute manifest caches the searchable list, so the
     * very first request of a test pays for things no later one does. The
     * measurement has to be of a steady state, or it measures the warm-up.
     *
     * @return array{count: int, bindings: int}
     */
    private function budgetFor(string $url): array
    {
        $this->get($url)->assertOk();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = count($query->bindings);
        });

        $this->get($url)->assertOk();

        // Nothing else in the suite wants this listener; the container is
        // rebuilt between tests, but not between the two requests above.
        DB::flushQueryLog();

        return ['count' => count($queries), 'bindings' => $queries === [] ? 0 : max($queries)];
    }

    private function collectionOf(string $name, int $products): string
    {
        // LunarDefaultsSeeder stops short of a collection group (`lunar:install`
        // creates one in a real install), so the group is made here, as the
        // other listing tests do.
        $group = CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Main', 'handle' => 'main']);

        $collection = Collection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => collect(['name' => new Text($name)]),
        ]);

        for ($i = 0; $i < $products; $i++) {
            $this->createDemoProduct("{$name} Product {$i}", 1000 + $i * 100)
                ->collections()
                ->attach($collection->id);
        }

        return (string) $collection->refresh()->defaultUrl?->slug;
    }

    public function test_a_listing_does_not_cost_a_query_per_product(): void
    {
        $slug = $this->collectionOf('Small', 3);
        $small = $this->budgetFor("/collections/{$slug}");

        // Four times the products on the page. With the relations the mapper
        // reads left to lazy-load, this was four times the queries.
        $slug = $this->collectionOf('Large', 12);
        $large = $this->budgetFor("/collections/{$slug}");

        $this->assertSame(
            $small['count'],
            $large['count'],
            "A listing of 12 products ran {$large['count']} queries against {$small['count']} for 3: "
            .'something the mapper reads is being lazy-loaded per product.',
        );
    }

    /**
     * The sitemap is one page whose size *is* the catalogue, so its query
     * count cannot be constant in the rows it returns - but the number of
     * parameters it sends can be, and that is what went wrong: the published
     * product ids were fetched into PHP and sent back one bind at a time.
     */
    public function test_the_sitemap_does_not_send_a_parameter_per_product(): void
    {
        $this->collectionOf('Small', 3);
        $small = $this->budgetFor('/sitemap.xml');

        $this->collectionOf('Large', 12);
        $large = $this->budgetFor('/sitemap.xml');

        $this->assertSame(
            $small['bindings'],
            $large['bindings'],
            "The sitemap's widest query carried {$large['bindings']} parameters for 15 products against "
            ."{$small['bindings']} for 3: the published set is being fetched and sent back rather than "
            .'left as a subquery.',
        );
    }
}
