<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Catalog;

use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductSummary;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Shared\Money;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\Facades\Pricing;
use Lunar\Facades\StorefrontSession;
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
