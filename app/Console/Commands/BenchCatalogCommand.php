<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

/**
 * Inflates the catalogue so a benchmark has something to measure.
 *
 * `benchmarks/` against the demo catalogue measures the framework: seven
 * products fit in any query plan and in PHP's memory, so every page costs
 * about the same and the numbers say nothing about the catalogue. The
 * interesting questions only appear at size - above all the one
 * `LunarProductCatalog::search()` raises about `SCOUT_DRIVER=collection`,
 * which has no index and matches substrings in PHP across every searchable
 * product on every request.
 *
 * So: a command, not a seeder. A seeder is part of installing this template
 * and runs in `DatabaseSeeder`; this is a tool you point at a throwaway
 * database, and `ConfirmableTrait` makes it say so out loud before it writes
 * to anything that calls itself production.
 *
 *   ./bin/artisan bench:catalog --products=2000
 *
 * Deliberately no images. Attaching one would mean a media conversion per
 * product (and `GeneratesPlaceholderImages` doing real GD work two thousand
 * times), which costs minutes and changes nothing about the queries being
 * measured - the product pages it generates carry no thumbnail, and that is
 * the one way these products are not like the demo ones.
 */
final class BenchCatalogCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'bench:catalog
        {--products=1000 : How many products to add}
        {--collections=5 : How many collections to spread them across}
        {--variants=2 : Variants per product}
        {--force : Skip the confirmation}';

    protected $description = 'Add generated products to the catalogue, so benchmarks have something to measure';

    /**
     * Nouns and adjectives rather than random strings: search has to find
     * these. A term like "cotton" matching a known fraction of the catalogue
     * is what makes `benchmarks/search.js` comparable between runs, and a
     * catalogue of `aX7fQ` would make every search a miss.
     */
    private const ADJECTIVES = ['Organic', 'Brushed', 'Heavyweight', 'Washed', 'Ribbed', 'Quilted', 'Oversized', 'Tailored', 'Padded', 'Lined'];

    private const MATERIALS = ['Cotton', 'Linen', 'Wool', 'Leather', 'Denim', 'Cashmere', 'Canvas', 'Silk'];

    private const ITEMS = ['Tee', 'Shirt', 'Jacket', 'Trousers', 'Scarf', 'Bag', 'Cap', 'Jumper', 'Coat', 'Mug'];

    public function handle(): int
    {
        if (! $this->confirmToProceed('Generating a benchmark catalogue')) {
            return self::FAILURE;
        }

        $wanted = (int) $this->option('products');
        $variantCount = max(1, (int) $this->option('variants'));

        $productType = ProductType::first();
        $currency = Currency::getDefault();
        $taxClass = TaxClass::where('default', true)->first() ?? TaxClass::first();

        if ($productType === null || $currency === null || $taxClass === null) {
            $this->components->error('Lunar has no base data yet - run `artisan db:seed` (or at least LunarDefaultsSeeder) first.');

            return self::FAILURE;
        }

        $collections = $this->collections((int) $this->option('collections'));
        $before = Product::query()->count();

        $this->components->info("Adding {$wanted} products to the {$before} already here.");

        $bar = $this->output->createProgressBar($wanted);
        $bar->start();

        for ($i = 0; $i < $wanted; $i++) {
            // One transaction per product rather than one for the lot: a
            // single transaction around thousands of products holds every row
            // it has written until the end, and an interrupted run then
            // leaves nothing behind - which is the opposite of what you want
            // from a tool you may well cancel halfway through.
            DB::transaction(function () use ($i, $before, $productType, $currency, $taxClass, $collections, $variantCount): void {
                $this->createProduct($i + $before, $productType->id, $currency->id, $taxClass->id, $collections[$i % count($collections)], $variantCount);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $total = Product::query()->count();

        $this->components->info("The catalogue now holds {$total} products across {$collections->count()} benchmark collections.");
        $this->components->bulletList([
            'Reindex if SCOUT_DRIVER is a real engine: artisan scout:import "Lunar\\Models\\Product"',
            'Then: BASE_URL=... ./bin/k6 run benchmarks/search.js',
        ]);

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Collection>
     */
    private function collections(int $wanted): \Illuminate\Support\Collection
    {
        $group = CollectionGroup::first();

        if ($group === null) {
            throw new \RuntimeException('No collection group: run LunarDefaultsSeeder first.');
        }

        return collect(range(1, max(1, $wanted)))->map(function (int $n) use ($group): Collection {
            $name = "Benchmark {$n}";

            // Reused if it is already there, so running the command twice
            // grows the catalogue instead of growing the navigation.
            // Typed locally for the same reason as in the Lunar adapters:
            // Lunar resolves its models at runtime, so static analysis only
            // sees an Eloquent Model here.
            /** @var Collection $collection */
            $collection = Collection::query()->whereJsonContains('attribute_data->name->value', $name)->first()
                ?? Collection::create([
                    'collection_group_id' => $group->id,
                    'attribute_data' => collect(['name' => new Text($name)]),
                ]);

            return $collection;
        });
    }

    private function createProduct(int $n, int $productTypeId, int $currencyId, int $taxClassId, Collection $collection, int $variantCount): void
    {
        $name = $this->name($n);

        $product = Product::create([
            'product_type_id' => $productTypeId,
            'status' => 'published',
            'attribute_data' => collect([
                'name' => new Text($name),
                'description' => new Text("<p>A generated product for load testing. {$name}, in the usual sizes, at a price nobody negotiated.</p>"),
            ]),
        ]);

        $product->collections()->attach($collection->id);

        // Prices spread deterministically across a plausible range, so the
        // price filters and the price sort in `browse.js` have something to
        // actually sort and filter - a catalogue priced identically makes
        // `sort=price_asc` free.
        $price = 1500 + ($n % 85) * 100;

        for ($v = 0; $v < $variantCount; $v++) {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->create([
                'tax_class_id' => $taxClassId,
                'sku' => 'BENCH-'.$n.'-'.$v,
                'unit_quantity' => 1,
                'shippable' => true,
                'purchasable' => 'in_stock',
                // Never zero: a variant out of stock cannot be added to a
                // cart, and the cart scenarios pick their variant from
                // whatever the catalogue offers.
                'stock' => 50 + ($n % 50),
            ]);

            $variant->prices()->create([
                'currency_id' => $currencyId,
                'price' => $price + $v * 200,
                'min_quantity' => 1,
            ]);
        }
    }

    /**
     * Names are a deterministic walk through three word lists, so the same
     * `--products=N` produces the same catalogue twice, and a term like
     * "Leather" matches a stable share of it.
     */
    private function name(int $n): string
    {
        $adjective = self::ADJECTIVES[$n % count(self::ADJECTIVES)];
        $material = self::MATERIALS[intdiv($n, count(self::ADJECTIVES)) % count(self::MATERIALS)];
        $item = self::ITEMS[intdiv($n, count(self::ADJECTIVES) * count(self::MATERIALS)) % count(self::ITEMS)];

        // The number keeps the slug unique: Lunar's UrlGenerator derives it
        // from the name, and a duplicate would collide on lunar_urls.
        return "{$adjective} {$material} {$item} {$n}";
    }
}
