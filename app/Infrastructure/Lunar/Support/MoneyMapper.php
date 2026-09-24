<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Support;

use App\Domain\Shared\Money;
use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\DataTypes\Price as LunarPrice;
use Lunar\Models\Currency;

/**
 * ONLY conversion point between Lunar's price types and our Money VO.
 * If Lunar is ever replaced, this file (and the rest of
 * `App\Infrastructure\Lunar`) is all that needs rewriting.
 */
final class MoneyMapper
{
    public function fromLunarPrice(LunarPrice $price): Money
    {
        return new Money(
            minorAmount: (int) $price->value,
            currencyCode: $price->currency->code,
            formatted: (string) $price->formatted(),
        );
    }

    /**
     * For an amount this application worked out itself - the sum of a
     * bundle's parts, say - which still has to be formatted the way every
     * other price in the shop is.
     */
    public function fromMinorUnits(int $amount, Currency $currency): Money
    {
        return $this->fromLunarPrice(new LunarPrice($amount, $currency, 1));
    }

    /**
     * `PricingResponse::$matched` is an Eloquent price row (not yet resolved
     * into a displayable amount): you have to ask it for the tax-exclusive
     * or tax-inclusive price. We take tax-exclusive here, consistent with
     * `lunar:install`'s default tax zone (`price_display: tax_exclusive`);
     * adapt if the project displays tax-inclusive prices.
     */
    public function fromPricingResponse(PricingResponse $pricing): Money
    {
        return $this->fromLunarPrice($pricing->matched->priceExTax());
    }
}
