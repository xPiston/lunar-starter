<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductBundle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * Two demo bundles, each priced below the sum of its parts.
 *
 * Built from whatever the catalogue seeder created rather than from fixed
 * ids, so they survive a reordered or extended demo catalogue - and from
 * different products each, because a bundle of two variants of the same
 * thing shows neither the idea nor a second photo.
 */
final class ProductBundleSeeder extends Seeder
{
    private const BUNDLES = [
        [
            'name' => 'Everyday Set',
            'slug' => 'everyday-set',
            'description' => 'The two pieces people order together anyway, for less than buying them one at a time.',
        ],
        [
            'name' => 'Weekend Kit',
            'slug' => 'weekend-kit',
            'description' => 'Something to wear and something to carry it in.',
        ],
    ];

    public function run(): void
    {
        $variants = Product::query()
            ->with(['variants.prices'])
            ->get()
            ->map(fn (Product $product): ?ProductVariant => $product->variants->first())
            ->filter()
            ->values();

        foreach (self::BUNDLES as $index => $definition) {
            // Two different products per bundle, taken in pairs.
            $parts = $variants->slice($index * 2, 2);

            if ($parts->count() < 2) {
                return;
            }

            $this->createBundle($definition, $parts);
        }
    }

    /**
     * @param  array{name: string, slug: string, description: string}  $definition
     * @param  Collection<int, ProductVariant>  $parts
     */
    private function createBundle(array $definition, $parts): void
    {
        ProductBundle::where('slug', $definition['slug'])->delete();

        $bundle = ProductBundle::create([...$definition, 'is_active' => true]);

        foreach ($parts as $variant) {
            $bundle->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);
        }

        // A tenth off what the parts cost separately - enough to read as an
        // offer without pretending to a discount nobody would believe.
        $separately = $parts->sum(
            fn (ProductVariant $variant): int => (int) ($variant->prices->first()?->price->value ?? 0)
        );

        Price::create([
            'price' => (int) round($separately * 0.9),
            'currency_id' => Currency::getDefault()?->id,
            'priceable_type' => $bundle->getMorphClass(),
            'priceable_id' => $bundle->id,
            'min_quantity' => 1,
        ]);
    }
}
