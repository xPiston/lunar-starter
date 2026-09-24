<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLine;
use App\Domain\Shared\Money;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\Base\Purchasable;
use Lunar\DataTypes\Price;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Cart as LunarCart;
use Lunar\Models\CartLine as LunarCartLine;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    /**
     * Reads the line through Lunar's `Purchasable` contract rather than
     * assuming a product variant: a line can just as well hold one of this
     * application's own bundles, and both answer these three questions.
     */
    private function toLine(LunarCartLine $line): CartLine
    {
        /** @var Purchasable $purchasable */
        $purchasable = $line->purchasable;

        return new CartLine(
            id: $line->id,
            name: (string) $purchasable->getDescription(),
            options: trim((string) $purchasable->getOption()),
            thumbnailUrl: $this->nullIfEmpty($this->thumbnailFor($purchasable)),
            quantity: $line->quantity,
            unitPrice: $this->priceOrZero($line->unitPrice),
            lineTotal: $this->priceOrZero($line->total),
        );
    }

    /**
     * Lunar's `Purchasable` contract documents this as returning a string,
     * but its own ProductVariant returns a Media object. Both shapes are
     * accepted, and the value is read as mixed precisely because the
     * docblock cannot be trusted here.
     */
    private function thumbnailFor(Purchasable $purchasable): string
    {
        /** @var mixed $thumbnail */
        $thumbnail = $purchasable->getThumbnail();

        if ($thumbnail instanceof Media) {
            return (string) $thumbnail->getUrl('small');
        }

        return (string) $thumbnail;
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
