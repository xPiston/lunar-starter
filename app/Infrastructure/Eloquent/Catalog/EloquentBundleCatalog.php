<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Catalog;

use App\Domain\Catalog\Bundle;
use App\Domain\Catalog\BundleItem;
use App\Domain\Catalog\Port\BundleCatalog;
use App\Domain\Shared\Money;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Lunar\Facades\Pricing;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Currency as LunarCurrency;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\ProductVariant as LunarProductVariant;
use Lunar\Models\Url;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * ADAPTER: bundles from the application's own tables, priced through Lunar.
 *
 * Under app/Infrastructure/Eloquent even though it touches Lunar's pricing:
 * the bundles themselves are ours, and what it borrows from Lunar is the
 * price resolution every other price in the shop goes through - so a
 * customer-group or currency rule applies to a bundle exactly as it does to a
 * variant.
 */
final readonly class EloquentBundleCatalog implements BundleCatalog
{
    public function __construct(private MoneyMapper $money) {}

    public function listActive(): array
    {
        return ProductBundle::query()
            ->active()
            ->with(['items.variant.product', 'prices'])
            ->orderBy('name')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function findBySlug(string $slug): ?Bundle
    {
        $bundle = ProductBundle::query()
            ->active()
            ->with(['items.variant.product', 'prices'])
            ->where('slug', $slug)
            ->first();

        return $bundle === null ? null : $this->toDomain($bundle);
    }

    private function toDomain(ProductBundle $bundle): Bundle
    {
        $inventory = $bundle->getTotalInventory();
        $price = $this->priceFor($bundle);
        $itemsTotal = $this->itemsTotal($bundle);

        return new Bundle(
            id: $bundle->id,
            name: $bundle->name,
            slug: $bundle->slug,
            description: $bundle->description,
            imageUrl: $bundle->imageUrl(),
            items: $bundle->items
                ->map(fn (ProductBundleItem $item): BundleItem => new BundleItem(
                    name: $item->variantName(),
                    quantity: $item->quantity,
                    productSlug: $this->productSlug($item),
                    imageUrl: $this->imageFor($item),
                ))
                ->all(),
            price: $price,
            itemsTotal: $itemsTotal,
            savings: $this->money->fromMinorUnits(
                max(0, $itemsTotal->minorAmount - $price->minorAmount),
                $this->currency(),
            ),
            // PHP_INT_MAX is how the model says "nothing here limits it" -
            // every part is `purchasable: always`. The domain expresses that
            // as null, the same as an unlimited product variant.
            availableStock: $inventory === PHP_INT_MAX ? null : $inventory,
        );
    }

    /**
     * The part's own photo, at full size rather than the small conversion:
     * these are laid out as a mosaic that stands in for the bundle's picture,
     * and a 200px crop blown up reads as a broken image.
     *
     * A bundle with no artwork of its own shows that mosaic instead, which is
     * why every part carries its picture rather than just the first.
     */
    private function imageFor(ProductBundleItem $item): ?string
    {
        /** @var ?LunarProductVariant $variant */
        $variant = $item->variant;
        /** @var ?LunarProduct $product */
        $product = $variant?->product;
        /** @var ?Media $media */
        $media = $product?->thumbnail;

        return $media?->getUrl() ?: null;
    }

    private function priceFor(ProductBundle $bundle): Money
    {
        $pricing = Pricing::for($bundle)
            ->currency(StorefrontSession::getCurrency())
            ->customerGroups(StorefrontSession::getCustomerGroups())
            ->get();

        return $this->money->fromPricingResponse($pricing);
    }

    /**
     * What the same contents cost bought one by one - the number the bundle's
     * price is meant to beat.
     */
    private function currency(): LunarCurrency
    {
        /** @var LunarCurrency $currency */
        $currency = StorefrontSession::getCurrency();

        return $currency;
    }

    private function itemsTotal(ProductBundle $bundle): Money
    {
        $currency = $this->currency();
        $total = 0;

        foreach ($bundle->items as $item) {
            /** @var ?LunarProductVariant $variant */
            $variant = $item->variant;

            if ($variant === null) {
                continue;
            }

            $price = Pricing::for($variant)
                ->currency($currency)
                ->customerGroups(StorefrontSession::getCustomerGroups())
                ->get();

            $total += $this->money->fromPricingResponse($price)->minorAmount * $item->quantity;
        }

        return $this->money->fromMinorUnits($total, $currency);
    }

    private function productSlug(ProductBundleItem $item): ?string
    {
        /** @var ?LunarProductVariant $variant */
        $variant = $item->variant;
        /** @var ?LunarProduct $product */
        $product = $variant?->product;

        /** @var ?Url $url */
        $url = $product?->defaultUrl;

        return $url === null ? null : (string) $url->slug;
    }
}
