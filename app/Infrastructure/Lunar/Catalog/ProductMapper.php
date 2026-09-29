<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Catalog;

use App\Domain\Catalog\PriceTier;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductSummary;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Shared\Money;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\Facades\Pricing;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Currency as LunarCurrency;
use Lunar\Models\Price as LunarPrice;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\ProductVariant as LunarProductVariant;

final class ProductMapper
{
    public function __construct(private readonly MoneyMapper $money) {}

    public function toSummary(LunarProduct $product): ProductSummary
    {
        $cheapestVariant = $product->variants
            ->sortBy(fn (LunarProductVariant $variant): int => $this->priceFor($variant)->minorAmount)
            ->first();

        $thumbnail = $product->getThumbnailImage();

        return new ProductSummary(
            id: $product->id,
            name: $product->translateAttribute('name'),
            slug: (string) $product->defaultUrl?->slug,
            thumbnailUrl: $thumbnail !== '' ? $thumbnail : null,
            priceFrom: $cheapestVariant ? $this->priceFor($cheapestVariant) : new Money(0, StorefrontSession::getCurrency()->code, ''),
            defaultVariantId: $cheapestVariant ? $cheapestVariant->id : 0,
        );
    }

    public function toDomain(LunarProduct $product): Product
    {
        $thumbnail = $product->getThumbnailImage();

        return new Product(
            id: $product->id,
            name: $product->translateAttribute('name'),
            slug: (string) $product->defaultUrl?->slug,
            description: $product->translateAttribute('description') ?: null,
            thumbnailUrl: $thumbnail !== '' ? $thumbnail : null,
            images: $product->getMedia(config('lunar.media.collection'))
                ->map(fn ($media): string => $media->getUrl())
                ->all(),
            variants: $product->variants
                ->map(fn (LunarProductVariant $variant): ProductVariant => $this->toVariant($variant))
                ->all(),
        );
    }

    public function toVariant(LunarProductVariant $variant): ProductVariant
    {
        return new ProductVariant(
            id: $variant->id,
            sku: $variant->sku ?? '',
            optionSummary: $variant->getOption(),
            price: $this->priceFor($variant),
            availableStock: $variant->purchasable === 'always' ? null : max(0, $variant->getTotalInventory()),
            tiers: $this->tiersFor($variant),
        );
    }

    /**
     * The quantity steps a shop set on this variant, read from Lunar rather
     * than computed: `lunar_prices.min_quantity` is its own price-break
     * mechanism, managed on the product's Pricing tab in the admin, and its
     * pricing manager already charges the right one when a cart line reaches
     * that quantity. This only puts them on the page.
     *
     * Returns nothing at all unless the shop set a break - a single step is
     * just the ordinary price, and rendering a one-card "pack" chooser for it
     * would invent an offer that does not exist.
     *
     * @return PriceTier[]
     */
    private function tiersFor(LunarProductVariant $variant): array
    {
        $pricing = Pricing::for($variant)
            ->currency(StorefrontSession::getCurrency())
            ->customerGroups(StorefrontSession::getCustomerGroups())
            ->get();

        $breaks = $pricing->priceBreaks
            ->sortBy('min_quantity')
            ->values();

        if ($breaks->isEmpty()) {
            return [];
        }

        /** @var LunarPrice $basePrice */
        $basePrice = $pricing->base;
        $base = $this->money->fromLunarPrice($basePrice->price);

        // The single-unit price leads the list: a customer compares packs
        // against buying one, so it has to be one of the cards.
        $tiers = [$this->tier(1, $base, $base)];

        foreach ($breaks as $break) {
            /** @var LunarPrice $break */
            $tiers[] = $this->tier(
                (int) $break->min_quantity,
                $this->money->fromLunarPrice($break->price),
                $base,
            );
        }

        return $tiers;
    }

    private function tier(int $quantity, Money $unit, Money $baseUnit): PriceTier
    {
        $total = $unit->minorAmount * $quantity;
        $undiscounted = $baseUnit->minorAmount * $quantity;
        /** @var LunarCurrency $currency */
        $currency = StorefrontSession::getCurrency();

        return new PriceTier(
            quantity: $quantity,
            unitPrice: $unit,
            total: $this->money->fromMinorUnits($total, $currency),
            undiscountedTotal: $this->money->fromMinorUnits($undiscounted, $currency),
            // Rounded down: a step that saves 9.6% advertises 9%, never 10%.
            percentOff: $undiscounted === 0 ? 0 : (int) floor((($undiscounted - $total) / $undiscounted) * 100),
        );
    }

    private function priceFor(LunarProductVariant $variant): Money
    {
        $pricing = Pricing::for($variant)
            ->currency(StorefrontSession::getCurrency())
            ->customerGroups(StorefrontSession::getCustomerGroups())
            ->get();

        return $this->money->fromPricingResponse($pricing);
    }
}
