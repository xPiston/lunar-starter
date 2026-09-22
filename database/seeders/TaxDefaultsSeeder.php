<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

/**
 * `lunar:install` creates the default TaxZone/TaxClass but deliberately no
 * TaxRate/TaxRateAmount - the actual percentage is business-specific (VAT,
 * sales tax, GST...) and Lunar leaves that entirely to the store. Without
 * this, tax is silently 0% forever: nothing errors, the checkout total is
 * just wrong, which is easy to miss precisely because it doesn't crash.
 *
 * Seeds ONE demo rate (20%, matching UK/EU standard VAT) on the default
 * zone/class so the template calculates real tax out of the box. A real
 * store replaces this with its actual rate(s) at /lunar/tax-rates - often
 * several, split across additional tax zones for different countries/states.
 *
 * Safe to run standalone in production (no factories/Faker involved), same
 * as ShippingDefaultsSeeder: `php artisan db:seed --class=TaxDefaultsSeeder --force`.
 */
class TaxDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $taxRate = TaxRate::create([
            'tax_zone_id' => TaxZone::getDefault()->id,
            'name' => 'Standard Rate',
            'priority' => 1,
        ]);

        TaxRateAmount::create([
            'tax_rate_id' => $taxRate->id,
            'tax_class_id' => TaxClass::getDefault()->id,
            'percentage' => 20,
        ]);
    }
}
