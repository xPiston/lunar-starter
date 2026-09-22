<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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
 * Demo data for the template (equivalent of the previous POCs' fixtures/
 * seeders). Uses Lunar models directly: this is a SETUP script, not
 * hexagonal runtime code, so it doesn't need to go through the domain
 * ports (exactly like AppFixtures on the poc-hexa-symfony side would insert
 * its ResourceRecord/UserRecord directly).
 *
 * Not used by the test suite or CI (those seed only LunarDefaultsSeeder),
 * which is why it can afford to reach the network for product photos.
 */
final class DemoCatalogSeeder extends Seeder
{
    /**
     * Sizes/colours are real `ProductOption` + `ProductOptionValue` rows, the
     * same ones the admin panel creates at /lunar/product-options - a variant
     * "is" its set of option values, which is what `ProductVariant::getOption()`
     * joins into the label the storefront shows.
     */
    private const OPTIONS = [
        'Size' => ['XS', 'S', 'M', 'L', 'XL'],
        'Colour' => ['Black', 'Charcoal', 'Sand'],
    ];

    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $currency = Currency::getDefault();
        $productType = ProductType::first();
        $taxClass = TaxClass::where('default', true)->first() ?? TaxClass::first();
        $collectionGroupId = CollectionGroup::first()->id;

        $options = $this->createOptions();

        $shirts = $this->createCollection('Shirts & Tees', $collectionGroupId);
        $accessories = $this->createCollection('Accessories', $collectionGroupId);

        foreach ($this->catalog($shirts, $accessories) as $item) {
            $product = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => collect([
                    'name' => new Text($item['name']),
                    'description' => new Text($item['description']),
                ]),
            ]);

            $product->collections()->attach($item['collection']->id);

            if ($item['option'] !== null) {
                $product->productOptions()->attach($options[$item['option']]->id, ['position' => 1]);
            }

            foreach ($item['variants'] as $variant) {
                $this->createVariant(
                    product: $product,
                    taxClass: $taxClass,
                    currency: $currency,
                    sku: $this->sku($item['name'], $variant['value'] ?? null, $product->id),
                    priceInCents: $variant['price'],
                    stock: $variant['stock'],
                    optionValue: $variant['value'] === null
                        ? null
                        : $options[$item['option']]->values->firstWhere(fn (ProductOptionValue $value): bool => $value->translate('name') === $variant['value']),
                );
            }

            $this->attachImages($product, $item['name'], $item['photos']);
        }
    }

    /**
     * Stock is deliberately varied to demonstrate all three storefront states
     * out of the box: plenty in stock, low stock ("Only N left"), and out of
     * stock (disabled "Add to cart") - now per variant, so a product can be
     * available in some sizes and sold out in others.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(Collection $shirts, Collection $accessories): array
    {
        $cdn = 'https://cdn.dummyjson.com/product-images';

        return [
            [
                'collection' => $shirts,
                'name' => 'Graphic Print Tee',
                'photos' => ["{$cdn}/mens-shirts/gigabyte-aorus-men-tshirt/1.webp", "{$cdn}/mens-shirts/gigabyte-aorus-men-tshirt/2.webp"],
                'option' => 'Size',
                'description' => <<<'HTML'
                    <p>A heavyweight 240gsm organic cotton tee with a screen-printed graphic that
                    softens a little more with every wash. Cut slightly boxy through the body, with
                    a ribbed collar that keeps its shape.</p>
                    <ul>
                        <li>100% GOTS-certified organic cotton</li>
                        <li>Water-based ink, printed in small batches</li>
                        <li>Pre-shrunk — take your usual size</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => 'S', 'price' => 2499, 'stock' => 12],
                    ['value' => 'M', 'price' => 2499, 'stock' => 25],
                    ['value' => 'L', 'price' => 2499, 'stock' => 4],
                    ['value' => 'XL', 'price' => 2699, 'stock' => 0],
                ],
            ],
            [
                'collection' => $shirts,
                'name' => 'Blue Check Shirt',
                'photos' => ["{$cdn}/mens-shirts/blue-&-black-check-shirt/1.webp", "{$cdn}/mens-shirts/blue-&-black-check-shirt/2.webp"],
                'option' => 'Size',
                'description' => <<<'HTML'
                    <p>Brushed cotton flannel in a washed blue check, cut straight enough to wear
                    open over a tee and tidy enough to button up. The collar is lightly fused, so it
                    stands without feeling stiff.</p>
                    <ul>
                        <li>Brushed cotton flannel, 170gsm</li>
                        <li>Corozo buttons, spare stitched inside the placket</li>
                        <li>Regular fit</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => 'S', 'price' => 4999, 'stock' => 18],
                    ['value' => 'M', 'price' => 4999, 'stock' => 9],
                    ['value' => 'L', 'price' => 4999, 'stock' => 14],
                ],
            ],
            [
                'collection' => $shirts,
                'name' => 'Red Plaid Shirt',
                'photos' => ["{$cdn}/mens-shirts/man-plaid-shirt/1.webp", "{$cdn}/mens-shirts/man-plaid-shirt/2.webp"],
                'option' => 'Size',
                'description' => <<<'HTML'
                    <p>The buffalo check everyone owns one of, done properly: yarn-dyed rather than
                    printed, so the pattern runs through the cloth and the red stays red after a
                    winter of washing.</p>
                    <ul>
                        <li>Yarn-dyed cotton, mid-weight</li>
                        <li>Chest pocket, curved hem</li>
                        <li>Relaxed fit</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => 'S', 'price' => 4499, 'stock' => 3],
                    ['value' => 'M', 'price' => 4499, 'stock' => 2],
                    ['value' => 'L', 'price' => 4499, 'stock' => 21],
                ],
            ],
            [
                'collection' => $accessories,
                'name' => 'Leather Tote Bag',
                'photos' => ["{$cdn}/womens-bags/heshe-women's-leather-bag/1.webp", "{$cdn}/womens-bags/heshe-women's-leather-bag/2.webp"],
                'option' => null,
                'description' => <<<'HTML'
                    <p>Full-grain leather that starts stiff and ends up moulded to whatever you
                    carry in it. Wide enough for a 15" laptop, with a zipped inner pocket for the
                    things that otherwise live at the bottom.</p>
                    <ul>
                        <li>Full-grain leather, unlined body</li>
                        <li>Zipped inner pocket, boxed base</li>
                        <li>Shoulder-length handles</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => null, 'price' => 12900, 'stock' => 6],
                ],
            ],
            [
                'collection' => $accessories,
                'name' => 'Aviator Sunglasses',
                'photos' => ["{$cdn}/sunglasses/classic-sun-glasses/1.webp", "{$cdn}/sunglasses/classic-sun-glasses/2.webp"],
                'option' => 'Colour',
                'description' => <<<'HTML'
                    <p>The shape that has outlived every trend since the 1930s, in a thin metal
                    frame with gradient lenses. Adjustable nose pads, spring hinges, and a hard case
                    you will lose within a month.</p>
                    <ul>
                        <li>CAT-3 gradient lenses, 100% UV400</li>
                        <li>Stainless steel frame, spring hinges</li>
                        <li>Hard case and cloth included</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => 'Black', 'price' => 8900, 'stock' => 11],
                    ['value' => 'Sand', 'price' => 8900, 'stock' => 2],
                ],
            ],
            [
                'collection' => $accessories,
                'name' => 'Faux Leather Backpack',
                'photos' => ["{$cdn}/womens-bags/white-faux-leather-backpack/1.webp", "{$cdn}/womens-bags/white-faux-leather-backpack/2.webp"],
                'option' => null,
                'description' => <<<'HTML'
                    <p>A clean-lined backpack in vegan leather with a padded sleeve and a magnetic
                    flap that stays shut on a bike. Straps are wide enough to carry a full load
                    without cutting into a shoulder.</p>
                    <ul>
                        <li>Vegan leather, water-repellent finish</li>
                        <li>Padded 14" laptop sleeve</li>
                        <li>Magnetic flap with concealed zip</li>
                    </ul>
                    HTML,
                'variants' => [
                    ['value' => null, 'price' => 7900, 'stock' => 0],
                ],
            ],
        ];
    }

    /**
     * @return array<string, ProductOption>
     */
    private function createOptions(): array
    {
        $options = [];

        foreach (self::OPTIONS as $name => $values) {
            $option = ProductOption::create([
                'name' => ['en' => $name],
                'label' => ['en' => $name],
                'handle' => strtolower($name),
                'shared' => true,
            ]);

            foreach ($values as $position => $value) {
                $option->values()->create([
                    'name' => ['en' => $value],
                    'position' => $position + 1,
                ]);
            }

            $options[$name] = $option->load('values');
        }

        return $options;
    }

    private function createVariant(
        Product $product,
        TaxClass $taxClass,
        Currency $currency,
        string $sku,
        int $priceInCents,
        int $stock,
        ?ProductOptionValue $optionValue,
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

        if ($optionValue !== null) {
            $variant->values()->attach($optionValue->id);
        }
    }

    private function sku(string $name, ?string $value, int $productId): string
    {
        $base = strtoupper(str_replace(' ', '-', $name)).'-'.$productId;

        return $value === null ? $base : $base.'-'.strtoupper($value);
    }

    /**
     * Downloads real product photos so the demo store doesn't look like a
     * colour-swatch test page, and falls back to a generated placeholder when
     * there's no network (offline dev, sandboxed CI) - seeding must never
     * fail because a third party is unreachable.
     *
     * Photos are fetched at seed time rather than committed: they stay out of
     * the repository (size, and someone else's licence), and land only in the
     * developer's own storage.
     *
     * Each URL is pinned to a specific product shot rather than a keyword
     * search, so every image genuinely shows the item it's attached to and the
     * two photos of a product are the same object from two angles - the
     * product page shows them as a gallery. The first becomes the thumbnail:
     * `Lunar\Observers\MediaObserver` marks it "primary" automatically. A real
     * store replaces these at /lunar/products/{id} - nothing about how images
     * are stored changes.
     *
     * @param  string[]  $urls
     */
    private function attachImages(Product $product, string $name, array $urls): void
    {
        foreach ($urls as $url) {
            $path = $this->downloadPhoto($url) ?? $this->generatePlaceholder($name);

            $product->addMedia($path)->usingName($name)->toMediaCollection('images');
        }
    }

    private function downloadPhoto(string $url): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 10, 'follow_location' => 1, 'ignore_errors' => true],
        ]);

        // Apostrophes and ampersands appear in some of these paths; the rest
        // of the URL is already encoded.
        $body = @file_get_contents(str_replace([' ', "'"], ['%20', '%27'], $url), false, $context);

        // Anything that isn't a readable image (offline, rate limit, an error
        // page served with a 200) falls through to the placeholder. Decoding
        // webp here is also what proves the GD build can handle the format the
        // media-library conversions will need.
        if ($body === false || @imagecreatefromstring($body) === false) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'lunar-demo-').'.webp';
        file_put_contents($path, $body);

        return $path;
    }

    private function generatePlaceholder(string $name): string
    {
        $width = 800;
        $height = 800;

        $image = imagecreatetruecolor($width, $height);

        $palette = [
            [30, 64, 175], [190, 24, 93], [21, 128, 61],
            [161, 98, 7], [55, 48, 163], [15, 118, 110],
        ];
        [$r, $g, $b] = $palette[crc32($name) % count($palette)];
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5;
        $lines = explode("\n", wordwrap($name, 18));
        $lineHeight = imagefontheight($font) + 6;
        $startY = (int) (($height - count($lines) * $lineHeight) / 2);

        foreach ($lines as $i => $line) {
            $textWidth = imagefontwidth($font) * strlen($line);
            imagestring($image, $font, (int) (($width - $textWidth) / 2), $startY + $i * $lineHeight, $line, $white);
        }

        $path = tempnam(sys_get_temp_dir(), 'lunar-demo-').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
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
}
