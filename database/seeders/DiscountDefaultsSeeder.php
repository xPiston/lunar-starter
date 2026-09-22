<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Discount;

/**
 * Seeds one working coupon so the cart's discount box isn't a dead end in a
 * fresh install: without a single Discount row, every code entered is
 * rejected and the feature looks broken rather than unused.
 *
 * `WELCOME10` takes 10% off, with no product or minimum-spend restriction so
 * it applies to whatever is in the cart. Lunar's `HasChannels`/`HasCustomerGroups`
 * traits attach a new discount to the default channel and customer group on
 * creation, which is why nothing is wired up by hand here.
 *
 * A real store creates its own at /lunar/discounts, where the restrictions
 * this one deliberately skips (dates, usage caps, eligible products) live.
 *
 * Safe to run standalone in production (no factories/Faker involved), same
 * as ShippingDefaultsSeeder and TaxDefaultsSeeder:
 * `php artisan db:seed --class=DiscountDefaultsSeeder --force`.
 */
class DiscountDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        Discount::create([
            'name' => 'Welcome offer',
            'handle' => 'welcome-offer',
            'coupon' => 'WELCOME10',
            'type' => AmountOff::class,
            'starts_at' => now(),
            'data' => ['percentage' => 10],
        ]);
    }
}
