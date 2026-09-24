<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\ProductVariant;

/**
 * One part of a bundle: a variant, and how many of it go in the box.
 *
 * @property int $id
 * @property int $product_bundle_id
 * @property int $product_variant_id
 * @property int $quantity
 * @property ?ProductVariant $variant
 */
class ProductBundleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_bundle_id',
        'product_variant_id',
        'quantity',
    ];

    /**
     * Resolved through Lunar's own model binding, so a project that swaps the
     * variant model keeps working - which is also why the generic here is the
     * base Model: the concrete class is a runtime decision that static
     * analysis cannot follow.
     *
     * @return BelongsTo<Model, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::modelClass(), 'product_variant_id');
    }

    /**
     * The name a customer would recognise: the product, plus the option when
     * there is more than one variant of it.
     */
    public function variantName(): string
    {
        /** @var ?ProductVariant $variant */
        $variant = $this->variant;

        if ($variant === null) {
            return 'Unavailable item';
        }

        /** @var ?LunarProduct $product */
        $product = $variant->product;
        $name = (string) $product?->translateAttribute('name');
        $option = trim((string) $variant->getOption());

        return $option === '' ? $name : "{$name} ({$option})";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }
}
