<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductBundleResource\Pages\Concerns;

use App\Models\ProductBundle;
use Lunar\Models\Currency;
use Lunar\Models\Price;

/**
 * Reads and writes the bundle's price.
 *
 * `bundle_price` is a form field, not a column: the amount lives in Lunar's
 * polymorphic price table, the same one variants use, so currency and
 * customer-group rules apply to a bundle unchanged. Staff shouldn't have to
 * know that, hence this translation on the way in and out.
 *
 * Entered in whole units and stored in minor ones, like every other price
 * crossing a form in this application.
 */
trait EditsBundlePrice
{
    protected function priceFor(ProductBundle $bundle): ?float
    {
        /** @var ?Price $price */
        $price = $bundle->prices()
            ->where('currency_id', Currency::getDefault()?->id)
            ->first();

        return $price === null ? null : $price->price->value / 100;
    }

    protected function storePrice(ProductBundle $bundle, mixed $amount): void
    {
        $currency = Currency::getDefault();

        if ($currency === null || $amount === null || $amount === '') {
            return;
        }

        Price::updateOrCreate(
            [
                'priceable_type' => $bundle->getMorphClass(),
                'priceable_id' => $bundle->id,
                'currency_id' => $currency->id,
                'min_quantity' => 1,
                'customer_group_id' => null,
            ],
            ['price' => (int) round(((float) $amount) * 100)],
        );
    }
}
