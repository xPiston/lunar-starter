<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Lunar\Base\Purchasable;
use Lunar\Base\Traits\HasPrices;
use Lunar\Models\Contracts\TaxClass as TaxClassContract;
use Lunar\Models\Price;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\TaxClass;

/**
 * Several product variants sold together at their own price.
 *
 * Implements Lunar's `Purchasable`, which is what makes this work without
 * touching Lunar: a cart line points at any purchasable through a morph, and
 * Lunar's own validators branch on whether that purchasable is a variant. A
 * bundle therefore flows through the cart, pricing, tax, shipping and order
 * pipelines unmodified.
 *
 * Two things a bundle does differently from a variant, and they are the whole
 * point of the feature:
 *
 *  - its price is its own, set in Lunar's polymorphic price table, not the
 *    sum of its parts - that difference is the offer;
 *  - its availability is the scarcest of its parts. Three of a bundle that
 *    contains two belts means six belts, and the customer must be told before
 *    checkout, not after.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property ?string $image_path
 * @property bool $is_active
 * @property EloquentCollection<int, ProductBundleItem> $items
 */
class ProductBundle extends Model implements Purchasable
{
    use HasPrices;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'is_active',
    ];

    /**
     * @param  Builder<ProductBundle>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<ProductBundleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
    }

    // ---------------------------------------------------------------------
    // Lunar\Base\Purchasable
    // ---------------------------------------------------------------------

    public function getPrices(): Collection
    {
        /** @var Collection<int, Price> $prices */
        $prices = $this->prices;

        return $prices;
    }

    public function getUnitQuantity(): int
    {
        return 1;
    }

    public function getTaxClass(): TaxClassContract
    {
        // The default class rather than one per bundle: a bundle's parts can
        // sit in different tax classes, and picking one of them would be
        // arbitrary. A shop with mixed rates should price bundles per class,
        // which is a decision for that shop rather than a default here.
        return TaxClass::getDefault();
    }

    public function getTaxReference()
    {
        return null;
    }

    public function getType()
    {
        return 'bundle';
    }

    public function getDescription()
    {
        return $this->name;
    }

    public function getOption()
    {
        // What the cart shows under the line's name: the contents, so someone
        // reviewing their basket can see what they are actually buying.
        return $this->items
            ->map(fn (ProductBundleItem $item): string => $item->quantity.'x '.$item->variantName())
            ->join(', ');
    }

    public function getOptions(): Collection
    {
        return collect();
    }

    public function getIdentifier()
    {
        return $this->slug;
    }

    public function isShippable()
    {
        // A bundle ships if anything in it does. A box with one physical item
        // and one download still has to be posted.
        return $this->items->contains(fn (ProductBundleItem $item): bool => (bool) $item->variant?->shippable);
    }

    public function getThumbnail()
    {
        /** @var ?LunarProduct $product */
        $product = $this->items->first()?->variant?->product;

        return $this->imageUrl() ?? (string) $product?->getThumbnailImage();
    }

    public function canBeFulfilledAtQuantity(int $quantity): bool
    {
        return $quantity <= $this->getTotalInventory();
    }

    /**
     * How many of this bundle could be shipped today: the scarcest part
     * decides.
     *
     * A part marked purchasable `always` places no limit - it is the shop
     * saying it can always supply that one - so it is skipped rather than
     * counted as zero.
     */
    public function getTotalInventory(): int
    {
        if ($this->items->isEmpty()) {
            return 0;
        }

        $available = [];

        foreach ($this->items as $item) {
            $variant = $item->variant;

            if ($variant === null) {
                // A part that no longer exists cannot be shipped, so neither
                // can the bundle.
                return 0;
            }

            if ($variant->purchasable === 'always') {
                continue;
            }

            $available[] = intdiv(max(0, $variant->getTotalInventory()), max(1, $item->quantity));
        }

        return $available === [] ? PHP_INT_MAX : min($available);
    }

    public function isPurchasable(): bool
    {
        return $this->is_active && $this->items->isNotEmpty();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
