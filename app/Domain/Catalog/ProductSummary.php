<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/**
 * Lightweight product projection for listing grids (home, collection). Does
 * not carry full variants/images: see Product for the detailed product
 * sheet.
 */
final readonly class ProductSummary
{
    /**
     * @param  int  $defaultVariantId  The cheapest variant's id, so listing
     *                                 grids can offer a quick "Add to cart" without a second page load. 0
     *                                 when the product somehow has no variant (shouldn't happen in practice,
     *                                 but VOs don't get to assume the impossible) - the frontend hides the
     *                                 button in that case.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $thumbnailUrl,
        public Money $priceFrom,
        public int $defaultVariantId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'thumbnail_url' => $this->thumbnailUrl,
            'price_from' => $this->priceFrom->toArray(),
            'default_variant_id' => $this->defaultVariantId,
        ];
    }
}
