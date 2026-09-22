<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;

/**
 * Demo data for the template (equivalent of the previous POCs' fixtures/
 * seeders). Uses Lunar models directly: this is a SETUP script, not
 * hexagonal runtime code, so it doesn't need to go through the domain
 * ports (exactly like AppFixtures on the poc-hexa-symfony side would insert
 * its ResourceRecord/UserRecord directly).
 */
final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $currency = Currency::getDefault();
        $productType = ProductType::first();
        $taxClass = TaxClass::where('default', true)->first() ?? TaxClass::first();
        $collectionGroupId = CollectionGroup::first()->id;

        $tshirts = $this->createCollection('T-Shirts', $collectionGroupId);
        $accessories = $this->createCollection('Accessories', $collectionGroupId);

        // Stock is deliberately varied here to demonstrate all three
        // storefront states out of the box: plenty in stock, low stock
        // ("Only N left"), and out of stock (disabled "Add to cart").
        $catalog = [
            [$tshirts, 'Black Graphic T-Shirt', 'An organic cotton t-shirt with a graphic print.', 2499, 25],
            [$tshirts, 'White Graphic T-Shirt', 'Same cut, off-white colourway.', 2499, 25],
            [$tshirts, 'Navy Plain T-Shirt', 'Timeless basic in combed cotton.', 1999, 3],
            [$accessories, 'Embroidered Cap', 'Adjustable cap with logo embroidery.', 1499, 40],
            [$accessories, 'Canvas Tote Bag', 'Heavyweight canvas bag with reinforced handles.', 999, 0],
            [$accessories, 'Ribbed Beanie', 'Ribbed knit beanie, one size.', 1299, 15],
        ];

        foreach ($catalog as [$collection, $name, $description, $priceInCents, $stock]) {
            $product = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => collect([
                    'name' => new Text($name),
                    'description' => new Text($description),
                ]),
            ]);

            $product->collections()->attach($collection->id);

            $variant = $product->variants()->create([
                'tax_class_id' => $taxClass->id,
                'sku' => strtoupper(str_replace(' ', '-', $name)).'-'.$product->id,
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

            $this->attachPlaceholderImage($product, $name);
        }
    }

    /**
     * Generates a plain placeholder image (GD, no external asset/network
     * dependency - works offline and in CI) and attaches it to Lunar's
     * media library exactly like an admin uploading a real product photo
     * would: `addMedia()->toMediaCollection('images')` is the whole API,
     * `Lunar\Observers\MediaObserver` marks the first image "primary"
     * (thumbnail) automatically. A real store replaces these at
     * /lunar/products/{id} - nothing about how images are stored changes.
     */
    private function attachPlaceholderImage(Product $product, string $name): void
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

        $product->addMedia($path)->usingName($name)->toMediaCollection('images');
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
