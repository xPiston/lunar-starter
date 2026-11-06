<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * Three demo kits, each priced below the sum of its parts.
 *
 * Built by naming the products rather than by taking whichever happened to be
 * seeded first. The previous version sliced the catalogue in pairs, which was
 * the right call for seven products with no relationship to each other; with
 * three hundred, the first two are a cordless drill and a rotary hammer, and
 * "buy both" is not an offer anybody would take.
 *
 * A DIY shop sells kits because the parts genuinely go together: the drill
 * with the bits that fit it, the paint with what spreads it, the three pieces
 * of protective equipment nobody buys until they need them. A bundle whose
 * products are missing is skipped and said out loud, which is what keeps this
 * honest when the catalogue is edited.
 */
final class ProductBundleSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, slug: string, description: string, products: string[]}>
     */
    private const BUNDLES = [
        [
            'name' => 'Pack perçage-vissage',
            'slug' => 'pack-percage-vissage',
            'description' => 'La perceuse, les forets des trois matériaux et les embouts qui vont avec : de quoi accrocher une étagère le jour où elle arrive, sans un second passage en magasin.',
            'products' => [
                'Perceuse-visseuse sans fil 18 V',
                'Coffret de 20 forets béton, bois et métal',
                'Coffret de 40 embouts de vissage',
            ],
        ],
        [
            'name' => 'Kit première peinture',
            'slug' => 'kit-premiere-peinture',
            'description' => 'Dix litres de mate, le rouleau avec son bac et le ruban qui fait les bords nets. Les trois achats d\'une pièce repeinte, au prix de deux et demi.',
            'products' => [
                'Peinture murale acrylique mate 10 L',
                'Rouleau mural 250 mm avec grille et bac',
                'Ruban de masquage 30 mm × 50 m',
            ],
        ],
        [
            'name' => 'Essentiels protection',
            'slug' => 'essentiels-protection',
            'description' => 'Lunettes, gants et casque antibruit. Ce que personne n\'achète avant d\'en avoir eu besoin une fois.',
            'products' => [
                'Lunettes de protection incolores',
                'Gants de manutention enduits polyuréthane',
                'Casque antibruit SNR 31 dB',
            ],
        ],
    ];

    public function run(): void
    {
        // Anything not in this list is a kit from a catalogue that no longer
        // exists. It was left behind the first time the shop changed trade,
        // and a bundle whose variants have been deleted is a storefront page
        // that adds nothing to a basket.
        ProductBundle::whereNotIn('slug', array_column(self::BUNDLES, 'slug'))->get()
            ->each(static function (ProductBundle $stale): void {
                $stale->items()->delete();
                // The price is a row of its own, keyed by morph, and nothing
                // cascades: deleting the bundle without it leaves a price
                // belonging to nobody — seven of them had already piled up.
                Price::where('priceable_type', $stale->getMorphClass())
                    ->where('priceable_id', $stale->id)
                    ->delete();
                $stale->delete();
            });

        foreach (self::BUNDLES as $definition) {
            $parts = $this->firstVariantsOf($definition['products']);

            if ($parts->count() < count($definition['products'])) {
                $this->command?->warn(
                    "Lot « {$definition['name']} » ignoré : un de ses produits n'est pas au catalogue."
                );

                continue;
            }

            $this->createBundle($definition, $parts);
        }
    }

    /**
     * The cheapest variant of each named product, in the order they were named.
     *
     * One query for the lot rather than one per name: a bundle of three
     * products should not cost three round trips, and the seeder is already
     * the slowest part of installing this template.
     *
     * @param  string[]  $names
     * @return Collection<int, ProductVariant>
     */
    private function firstVariantsOf(array $names): Collection
    {
        /** @var EloquentCollection<int, Product> $products */
        $products = Product::query()->with(['variants.prices'])->get();

        return collect($names)
            ->map(fn (string $name): ?ProductVariant => $products
                ->first(fn (Product $product): bool => $product->translateAttribute('name') === $name)
                ?->variants
                ->sortBy(fn (ProductVariant $variant): int => (int) ($variant->prices->first()?->price->value ?? 0))
                ->first())
            ->filter()
            ->values();
    }

    /**
     * @param  array{name: string, slug: string, description: string, products: string[]}  $definition
     * @param  Collection<int, ProductVariant>  $parts
     */
    private function createBundle(array $definition, Collection $parts): void
    {
        ProductBundle::where('slug', $definition['slug'])->delete();

        $bundle = ProductBundle::create([
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'description' => $definition['description'],
            'is_active' => true,
        ]);

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
