<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLine;
use App\Domain\Shared\Money;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\DataTypes\Price;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Cart as LunarCart;
use Lunar\Models\CartLine as LunarCartLine;
use Lunar\Models\ProductVariant as LunarProductVariant;

final class CartMapper
{
    public function __construct(private readonly MoneyMapper $money) {}

    public function toDomain(LunarCart $cart): Cart
    {
        return new Cart(
            id: $cart->id,
            lines: $cart->lines
                ->map($this->toLine(...))
                ->all(),
            subTotal: $this->priceOrZero($cart->subTotal),
            shippingTotal: $this->priceOrZero($cart->shippingTotal),
            discountTotal: $this->priceOrZero($cart->discountTotal),
            taxTotal: $this->priceOrZero($cart->taxTotal),
            total: $this->priceOrZero($cart->total),
            couponCode: $cart->coupon_code,
        );
    }

    private function toLine(LunarCartLine $line): CartLine
    {
        /** @var LunarProductVariant $variant */
        $variant = $line->purchasable;

        return new CartLine(
            id: $line->id,
            productVariantId: $variant->id,
            name: $variant->product->translateAttribute('name'),
            thumbnailUrl: $this->nullIfEmpty($variant->product->getThumbnailImage()),
            quantity: $line->quantity,
            unitPrice: $this->priceOrZero($line->unitPrice),
            lineTotal: $this->priceOrZero($line->total),
        );
    }

    private function priceOrZero(?Price $price): Money
    {
        return $price
            ? $this->money->fromLunarPrice($price)
            : new Money(0, StorefrontSession::getCurrency()->code, '');
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
