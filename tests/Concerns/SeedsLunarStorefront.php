<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

/**
 * Creates demo products for the Storefront contract tests. Lunar's base
 * data (channel, currency, tax zone...) is already seeded for any
 * RefreshDatabase database by Tests\TestCase::$seeder
 * (Database\Seeders\LunarDefaultsSeeder): this trait only needs to create
 * the products specific to each test.
 */
trait SeedsLunarStorefront
{
    /**
     * @param  int  $stock  Generous by default so tests unrelated to stock
     *                      never trip the `in_stock` validator; pass a low value to exercise it.
     */
    protected function createDemoProduct(string $name = 'Test T-Shirt', int $priceInCents = 2499, int $stock = 100): Product
    {
        $product = Product::create([
            'product_type_id' => ProductType::first()->id,
            'status' => 'published',
            'attribute_data' => collect([
                'name' => new Text($name),
                'description' => new Text('Description for '.$name),
            ]),
        ]);

        $variant = $product->variants()->create([
            'tax_class_id' => TaxClass::first()->id,
            'sku' => 'SKU-'.$product->id,
            'unit_quantity' => 1,
            'shippable' => true,
            'purchasable' => 'in_stock',
            'stock' => $stock,
        ]);

        $variant->prices()->create([
            'currency_id' => Currency::getDefault()->id,
            'price' => $priceInCents,
            'min_quantity' => 1,
        ]);

        return $product->fresh(['variants']);
    }

    protected function firstVariant(Product $product): ProductVariant
    {
        return $product->variants->first();
    }
}
