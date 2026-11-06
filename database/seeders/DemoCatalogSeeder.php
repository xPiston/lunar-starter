<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\FetchesCatalogPhotos;
use Database\Seeders\Concerns\GeneratesPlaceholderImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

/**
 * The demo catalogue: a French DIY shop, fourteen aisles deep.
 *
 * Uses Lunar models directly: this is a SETUP script, not hexagonal runtime
 * code, so it doesn't need to go through the domain ports (exactly like
 * AppFixtures on the poc-hexa-symfony side would insert its ResourceRecord
 * directly).
 *
 * **Data, not code.** The catalogue itself lives in `database/data/catalog/`,
 * one file per aisle, and this class only knows how to turn such a file into
 * Lunar rows. Adding an aisle is adding a file; three hundred products in a
 * `match` inside a seeder would be a file nobody could read or review.
 *
 * It replaced a seven-product clothing catalogue whose photos were pinned one
 * URL at a time to `cdn.dummyjson.com`. That is the right way to do seven
 * products and the wrong way to do three hundred and fifty — and dummyjson has
 * no DIY range at all, so none of those URLs could survive the change of
 * trade. Photography now comes from Wikimedia Commons by search, with the
 * filtering that makes a search usable described in
 * [FetchesCatalogPhotos].
 *
 * Not used by the test suite or CI (those seed only LunarDefaultsSeeder),
 * which is why it can afford to reach the network at all.
 */
final class DemoCatalogSeeder extends Seeder
{
    use FetchesCatalogPhotos;
    use GeneratesPlaceholderImages;

    /**
     * How many products per aisle, and how many photos each.
     *
     * Public so `demo:catalog` can turn the catalogue down while somebody is
     * working on it: a full seed is about seven hundred downloads and seven
     * hundred media conversions, which is a coffee on this machine and not
     * something to sit through to check one description.
     */
    public ?int $limitPerAisle = null;

    public int $photosPerProduct = 2;

    /** @var string[] Only these aisle files, by slug. Empty means all of them. */
    public array $onlyAisles = [];

    /**
     * How many products have already asked for each photo search.
     *
     * Hardware shares photo families by nature — every box of screws looks
     * like every other box of screws — so the second product to ask for one
     * starts further down the list of candidates instead of being given the
     * same picture as the first.
     *
     * @var array<string, int>
     */
    private array $photoQueriesUsed = [];

    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->warn('The catalogue already has products: nothing to do. `demo:catalog --fresh` starts over.');

            return;
        }

        $currency = $this->euro();
        $productType = ProductType::first();
        $taxClass = TaxClass::where('default', true)->first() ?? TaxClass::first();
        $collectionGroupId = CollectionGroup::first()->id;

        $aisles = $this->aisles();
        $options = $this->createOptions($aisles);

        foreach ($aisles as $aisle) {
            $collection = $this->createCollection($aisle['name'], $collectionGroupId);
            $products = $this->limitPerAisle === null
                ? $aisle['products']
                : array_slice($aisle['products'], 0, $this->limitPerAisle);

            $this->command?->info("{$aisle['name']} — ".count($products).' produits');

            foreach ($products as $item) {
                $this->createProduct($item, $collection, $productType, $taxClass, $currency, $options);
            }
        }
    }

    /**
     * The catalogue as data, for a caller that wants to inspect it rather
     * than seed it — `demo:catalog --titles` reads it to report photographs.
     *
     * @return array<int, array{slug: string, name: string, products: array<int, array<string, mixed>>}>
     */
    public function catalogue(): array
    {
        return $this->aisles();
    }

    /**
     * What one photo search would bring back, best first.
     *
     * @return array<int, array{title: string, score: int}>
     */
    public function choicesFor(string $query): array
    {
        return $this->photoChoices($query);
    }

    /**
     * The aisle files, in file-name order.
     *
     * Numbered prefixes, because the order is the shop's: the aisle a customer
     * sees first is the one a DIY shop puts by the door, and alphabetical order
     * would open on "Bois & panneaux".
     *
     * @return array<int, array{slug: string, name: string, products: array<int, array<string, mixed>>}>
     */
    private function aisles(): array
    {
        $files = glob(database_path('data/catalog/*.php')) ?: [];
        sort($files);

        $aisles = [];

        foreach ($files as $file) {
            $slug = (string) Str::of(basename($file, '.php'))->after('-');

            if ($this->onlyAisles !== [] && ! in_array($slug, $this->onlyAisles, true)) {
                continue;
            }

            /** @var array{name: string, products: array<int, array<string, mixed>>} $aisle */
            $aisle = require $file;
            $aisles[] = ['slug' => $slug, 'name' => $aisle['name'], 'products' => $aisle['products']];
        }

        return $aisles;
    }

    /**
     * Euros, and the default.
     *
     * `LunarDefaultsSeeder` installs US dollars, which is the right default for
     * a template that does not know what it sells. This catalogue does: a
     * French DIY shop priced in dollars is not a demonstration of anything.
     * The baseline is left alone so the test suite keeps the currency it
     * asserts on — nothing here runs in the suite.
     */
    private function euro(): Currency
    {
        $euro = Currency::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => false,
            ],
        );

        if (! $euro->default) {
            Currency::query()->where('default', true)->update(['default' => false]);
            $euro->update(['default' => true]);
        }

        return $euro->refresh();
    }

    /**
     * The product options the catalogue actually uses, derived from it.
     *
     * Nothing declares them: an option exists because some product offers a
     * choice, and its values are in the order they first appear, which is the
     * order a size range is written in. Declaring them separately would be a
     * second list to keep in step with the first.
     *
     * @param  array<int, array{products: array<int, array<string, mixed>>}>  $aisles
     * @return array<string, ProductOption>
     */
    private function createOptions(array $aisles): array
    {
        $values = [];

        foreach ($aisles as $aisle) {
            foreach ($aisle['products'] as $item) {
                if (($item['option'] ?? null) === null) {
                    continue;
                }

                foreach ($item['variants'] as [$value]) {
                    if ($value !== null && ! in_array($value, $values[$item['option']] ?? [], true)) {
                        $values[$item['option']][] = $value;
                    }
                }
            }
        }

        $options = [];

        foreach ($values as $name => $list) {
            $option = ProductOption::create([
                // French text under the `en` key, which is the one language
                // row Lunar installs and the one `translate()` reads. Adding a
                // `fr` row and making it the default would silence everything
                // the other seeders wrote under `en` — a bigger change than a
                // demo catalogue is allowed to make on its way past.
                'name' => ['en' => $name],
                'label' => ['en' => $name],
                'handle' => Str::slug($name),
                'shared' => true,
            ]);

            foreach ($list as $position => $value) {
                $option->values()->create([
                    'name' => ['en' => $value],
                    'position' => $position + 1,
                ]);
            }

            $options[$name] = $option->load('values');
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, ProductOption>  $options
     */
    private function createProduct(
        array $item,
        Collection $collection,
        ProductType $productType,
        TaxClass $taxClass,
        Currency $currency,
        array $options,
    ): void {
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => collect([
                'name' => new Text($item['name']),
                'description' => new Text($this->description($item)),
            ]),
        ]);

        $product->collections()->attach($collection->id);

        if (($item['option'] ?? null) !== null) {
            $product->productOptions()->attach($options[$item['option']]->id, ['position' => 1]);
        }

        foreach ($item['variants'] as [$value, $price, $stock]) {
            $this->createVariant(
                product: $product,
                taxClass: $taxClass,
                currency: $currency,
                sku: $this->sku($item['name'], $value, $product->id),
                priceInCents: $price,
                stock: $stock,
                optionValue: $value === null
                    ? null
                    : $options[$item['option']]->values->firstWhere(
                        fn (ProductOptionValue $option): bool => $option->translate('name') === $value,
                    ),
                priceBreaks: $item['breaks'] ?? [],
            );
        }

        $this->attachPhotos($product, $item);
    }

    /**
     * The product sheet: one paragraph, then the specifications.
     *
     * Assembled rather than written out, because the shape is the same for
     * every product and the three hundred copies of `<p>...</p><ul>` would be
     * three hundred chances to get the markup wrong in one of them. What is
     * written by hand is what differs: the sentence, and the specifications.
     *
     * @param  array<string, mixed>  $item
     */
    private function description(array $item): string
    {
        $specs = implode('', array_map(
            static fn (string $spec): string => '<li>'.e($spec).'</li>',
            $item['specs'] ?? [],
        ));

        return '<p>'.e($item['pitch']).'</p>'.($specs === '' ? '' : "<ul>{$specs}</ul>");
    }

    private function createCollection(string $name, int $collectionGroupId): Collection
    {
        return Collection::create([
            'collection_group_id' => $collectionGroupId,
            'attribute_data' => collect([
                'name' => new Text($name),
            ]),
        ]);
    }

    private function createVariant(
        Product $product,
        TaxClass $taxClass,
        Currency $currency,
        string $sku,
        int $priceInCents,
        int $stock,
        ?ProductOptionValue $optionValue,
        /** @var array<int, int> $priceBreaks quantity => percentage off */
        array $priceBreaks = [],
    ): void {
        /** @var ProductVariant $variant */
        $variant = $product->variants()->create([
            'tax_class_id' => $taxClass->id,
            'sku' => $sku,
            'unit_quantity' => 1,
            'shippable' => true,
            'purchasable' => 'in_stock',
            'stock' => $stock,
        ]);

        $variant->prices()->create([
            'currency_id' => $currency->id,
            'price' => $priceInCents,
            'min_quantity' => 1,
        ]);

        // Quantity pricing, where the catalogue asks for it. `min_quantity` is
        // Lunar's own price-break mechanism, managed on a product's Pricing tab
        // in the admin - nothing here is a parallel discount system. It earns
        // its place in a DIY shop: screws, tiles and paint are bought by the
        // box, and the box is cheaper per unit.
        foreach ($priceBreaks as $quantity => $percentOff) {
            $variant->prices()->create([
                'currency_id' => $currency->id,
                'price' => (int) round($priceInCents * (1 - $percentOff / 100)),
                'min_quantity' => $quantity,
            ]);
        }

        if ($optionValue !== null) {
            $variant->values()->attach($optionValue->id);
        }
    }

    private function sku(string $name, ?string $value, int $productId): string
    {
        $base = Str::upper(Str::slug(Str::ascii($name))).'-'.$productId;

        return $value === null ? $base : $base.'-'.Str::upper(Str::slug(Str::ascii($value)));
    }

    /**
     * Photographs, and the credit they carry.
     *
     * The licence lives on the media record rather than nowhere: these files
     * are somebody else's work under a Creative Commons or public-domain
     * licence, and a demo that strips the author's name is a demo that teaches
     * the wrong habit. `ContentPageSeeder` publishes them as a credits page.
     *
     * A product whose search found nothing falls back to a generated tile
     * carrying its own name — the same trait the banners use. Seeding must
     * never fail because somebody else's website is having a bad day.
     *
     * @param  array<string, mixed>  $item
     */
    private function attachPhotos(Product $product, array $item): void
    {
        $slug = Str::slug(Str::ascii($item['name']));

        $offset = ($this->photoQueriesUsed[$item['photo']] ?? 0) * $this->photosPerProduct;
        $this->photoQueriesUsed[$item['photo']] = ($this->photoQueriesUsed[$item['photo']] ?? 0) + 1;

        for ($rank = 0; $rank < $this->photosPerProduct; $rank++) {
            $path = $this->catalogPhoto($item['photo'], $offset + $rank, $slug);

            if ($path === null) {
                // Only the first photo is worth inventing. A product with one
                // real photograph and one coloured tile looks broken; with one
                // photograph it looks like a product.
                if ($rank > 0) {
                    continue;
                }

                $path = Storage::disk('public')->path($this->placeholderImage(
                    label: $item['name'],
                    directory: 'catalog-placeholders',
                    width: 1200,
                    height: 1200,
                    colours: [[38, 38, 38], [120, 53, 15]],
                ));
            }

            $product->addMedia($path)
                ->preservingOriginal()
                ->usingName($item['name'])
                ->withCustomProperties($this->photoCredit($slug, $offset + $rank))
                ->toMediaCollection('images');
        }
    }
}
