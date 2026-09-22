<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Currency;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;

/**
 * `lunar:install` doesn't seed anything shipping-related - table-rate
 * shipping is a separate, optional addon (see composer.json), so there's no
 * equivalent baseline to inherit the way there is for currencies/tax zones.
 * Shared by DatabaseSeeder (real dev DB) and LunarDefaultsSeeder (tests) so
 * both stay in sync with a single source of truth.
 *
 * "Unrestricted" zone type: applies regardless of destination country/state/
 * postcode - the simplest zone that still exercises the real package
 * (Lunar\Shipping\ShippingModifier), rather than one flat rate hardcoded in
 * PHP. A real store adds country/state/postcode-scoped zones and per-zone
 * rates through /lunar/shipping-zones - no code required for that part.
 */
class ShippingDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $zone = ShippingZone::create([
            'name' => 'Worldwide',
            'type' => 'unrestricted',
        ]);

        $this->createFlatRateMethod($zone, 'Standard Shipping', 'standard', 500, 'Delivery within 3 to 5 business days.');
        $this->createFlatRateMethod($zone, 'Express Shipping', 'express', 1500, 'Delivery within 1 to 2 business days.');
    }

    private function createFlatRateMethod(ShippingZone $zone, string $name, string $code, int $priceInCents, string $description): void
    {
        $method = ShippingMethod::create([
            'name' => $name,
            'description' => $description,
            'code' => $code,
            'driver' => 'flat-rate',
            'enabled' => true,
            'data' => [],
        ]);

        $rate = ShippingRate::create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'enabled' => true,
        ]);

        // min_quantity here is a subtotal threshold (in minor units), not a
        // literal item count: Lunar\Shipping\Drivers\ShippingMethods\FlatRate
        // prices a rate by cart subtotal via the same tiered-pricing table
        // products use. 1 matches any non-empty cart.
        $rate->prices()->create([
            'currency_id' => Currency::getDefault()->id,
            'price' => $priceInCents,
            'min_quantity' => 1,
        ]);
    }
}
