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
 *
 * These run under the drivers production uses, not the suite's. `phpunit.xml`
 * sets SESSION_DRIVER and CACHE_STORE to `array`, which is right for every
 * other test - it is faster and it isolates them - but it means a storefront
 * request in the suite never touches the session table, while
 * `.env.example` has it on `database`, where every page view is a `select`
 * and an `update` on `sessions`. A budget measured without them is a budget
 * that under-reports what a visitor actually costs, so this class opts back
 * in. Measured: two queries per request, and *zero* for the cache - nothing
 * on the storefront reads it, which is its own finding.
 */
final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'cache.default' => 'database',
            // Session garbage collection is a lottery - `[2, 100]` by
            // default, so two requests in a hundred also run a `delete from
            // sessions`. Left on, this class fails about one run in seven
            // with one query more than it budgeted, which is the worst thing
            // a budget can do: a test nobody trusts gets its number raised
            // until it asserts nothing. Production wants the lottery;
            // a measurement wants a number it can reproduce.
            'session.lottery' => [0, 100],
        ]);

        // The managers are resolved singletons by now, each holding the
        // driver it was built with; forgetting them is what makes the config
        // above take effect on the next request.
        foreach (['session', 'session.store', 'cache', 'cache.store'] as $resolved) {
            $this->app->forgetInstance($resolved);
        }
    }

    /**
     * Counts the queries one request makes, warming up first.
     *
     * The warm-up matters: Lunar memoises its channel, currency and customer
     * group, and the attribute manifest caches the searchable list, so the
     * very first request of a test pays for things no later one does. The
     * measurement has to be of a steady state, or it measures the warm-up.
     *
     * @return array<int, array{sql: string, bindings: int}>
     */
    private function queriesFor(string $url): array
    {
        $this->get($url)->assertOk();

        // A test reuses one application for both requests, so anything bound
        // `scoped` - the catalogue adapter, which memoises the navbar's
        // collection list - would still be warm from the warm-up and its
        // queries would not appear below. Production never is: classic
        // FrankenPHP builds a new application per request, and a worker-mode
        // runtime calls exactly this between them.
        $this->app->forgetScopedInstances();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'bindings' => count($query->bindings)];
        });

        $this->get($url)->assertOk();

        // Nothing else in the suite wants this listener; the container is
        // rebuilt between tests, but not between the two requests above.
        DB::flushQueryLog();

        return $queries;
    }

    /**
     * @return array{count: int, bindings: int}
     */
    private function budgetFor(string $url): array
    {
        $queries = $this->queriesFor($url);
        $bindings = array_column($queries, 'bindings');

        return ['count' => count($queries), 'bindings' => $bindings === [] ? 0 : max($bindings)];
    }

    /**
     * How many of a request's queries have the given table as their subject.
     *
     * The first `from` in a statement is the statement's own; any after it
     * belong to a subquery, and counting those is wrong - Lunar looks up its
     * discounts with a `where exists (select * from "lunar_collections" ...)`,
     * which is not a second read of the collection list.
     */
    private function readsOf(string $table, string $url): int
    {
        return count(array_filter(
            $this->queriesFor($url),
            function (array $query) use ($table): bool {
                preg_match('/\bfrom "([a-z_]+)"/', $query['sql'], $subject);

                return ($subject[1] ?? null) === $table;
            },
        ));
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

    /**
     * Every storefront page carries the same chrome - the navbar's
     * collections, the footer's editorial pages, the flag that says whether
     * there are bundles to link to - and none of it depends on the page. It
     * is the cheapest thing in the application to get wrong, because the cost
     * is paid on every request rather than on one page.
     *
     * This is the one the existing tests could not see: the home page read
     * the collection list twice, once for the navigation and once for its own
     * tiles, and since the duplication is constant in the number of products
     * the comparison above held perfectly while the page did twice the work.
     */
    public function test_the_navigation_is_read_once_per_request(): void
    {
        $this->collectionOf('Shared', 2);

        foreach (['/', '/cart'] as $url) {
            $this->assertSame(
                1,
                $this->readsOf('lunar_collections', $url),
                "{$url} queried lunar_collections more than once: two callers are asking for the navbar's "
                .'list without sharing it (see LunarProductCatalog::listCollections).',
            );
        }
    }

    /**
     * A page with nothing on it still costs the chrome, the session read and
     * the session write. That floor is what every visitor pays before the
     * page they asked for is considered, so it gets a ceiling of its own:
     * what trips this is not a multiplication but an accretion - one more
     * always-on lookup added to the layout, which no functional test would
     * ever notice.
     */
    public function test_an_empty_page_pays_only_for_the_chrome(): void
    {
        $budget = $this->budgetFor('/cart');

        $this->assertLessThanOrEqual(
            16,
            $budget['count'],
            "An empty cart ran {$budget['count']} queries. Twelve of them are the floor - the session read "
            .'and write, the navbar, the footer pages, the bundle flag and the empty cart itself; past '
            .'sixteen, something always-on has been added to every page in the shop.',
        );
    }
}
