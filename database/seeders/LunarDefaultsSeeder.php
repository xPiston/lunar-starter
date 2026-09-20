<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxZone;

/**
 * The strict minimum that `php artisan lunar:install` seeds in prod (see
 * vendor/lunarphp/core/src/Console/InstallLunar.php) for Lunar to work:
 * without these rows, anything touching a cart session or a price/tax
 * calculation (including simply logging a user in, which triggers
 * Lunar\Listeners\CartSessionAuthListener) throws on a freshly migrated
 * database.
 *
 * Test-only seeder (see tests/TestCase::$seeder): on a dev/prod database
 * this data already exists via `lunar:install`, so this seeder must not be
 * called again from DatabaseSeeder, or it would duplicate the "default"
 * rows.
 */
class LunarDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Channel::create([
            'name' => 'Webstore',
            'handle' => 'webstore',
            'default' => true,
            'url' => 'http://localhost',
        ]);

        Language::create([
            'code' => 'en',
            'name' => 'English',
            'default' => true,
        ]);

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'default' => true,
            'enabled' => true,
        ]);

        CustomerGroup::create([
            'name' => 'Retail',
            'handle' => 'retail',
            'default' => true,
        ]);

        TaxClass::create([
            'name' => 'Default Tax Class',
            'default' => true,
        ]);

        // Without a shipping address, Lunar\Actions\Taxes\GetTaxZone falls
        // back to TaxZone::getDefault() (no attached Country needed).
        TaxZone::create([
            'name' => 'Default Tax Zone',
            'zone_type' => 'country',
            'price_display' => 'tax_exclusive',
            'default' => true,
            'active' => true,
        ]);

        ProductType::create(['name' => 'Stock']);

        // Product search (App\Infrastructure\Lunar\Catalog\LunarProductCatalog::search())
        // relies on Lunar\Base\AttributeManifest::getSearchableAttributes(),
        // which reads real `attributes` rows flagged `searchable` (the
        // migration's own default) - not `attribute_data` content directly.
        // `lunar:install` creates exactly this "name"/"description" pair in
        // prod (see InstallLunar::handle()); mirrored here so tests exercise
        // the same lookup.
        $attributeGroup = AttributeGroup::create([
            'attributable_type' => Product::morphName(),
            'name' => collect(['en' => 'Details']),
            'handle' => 'details',
            'position' => 1,
        ]);

        Attribute::create([
            'attribute_type' => 'product',
            'attribute_group_id' => $attributeGroup->id,
            'position' => 1,
            'name' => ['en' => 'Name'],
            'handle' => 'name',
            'section' => 'main',
            'type' => TranslatedText::class,
            'required' => true,
            'system' => true,
            'configuration' => ['richtext' => false],
        ]);

        Attribute::create([
            'attribute_type' => 'product',
            'attribute_group_id' => $attributeGroup->id,
            'position' => 2,
            'name' => ['en' => 'Description'],
            'handle' => 'description',
            'section' => 'main',
            'type' => TranslatedText::class,
            'required' => false,
            'system' => false,
            'configuration' => ['richtext' => true],
        ]);

        // Required by the Checkout address forms (`lunar:install` imports
        // the full dataset via `lunar:import:address-data`; a single
        // country is enough for tests).
        Country::create([
            'name' => 'United Kingdom',
            'iso3' => 'GBR',
            'iso2' => 'GB',
            'phonecode' => '44',
            'currency' => 'GBP',
            'emoji' => '🇬🇧',
            'emoji_u' => 'U+1F1EC U+1F1E7',
        ]);

        $this->call(ShippingDefaultsSeeder::class);
        $this->call(TaxDefaultsSeeder::class);
    }
}
