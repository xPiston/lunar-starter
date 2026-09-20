<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

final readonly class ProductVariant
{
    /**
     * @param  ?int  $availableStock  How many units can currently be added
     *                                to a cart, or null when the product is unlimited/made-to-order (Lunar's
     *                                `purchasable = 'always'`, the default for a variant with no stock policy
     *                                configured).
     */
    public function __construct(
        public int $id,
        public string $sku,
        public string $optionSummary,
        public Money $price,
        public ?int $availableStock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'option_summary' => $this->optionSummary,
            'price' => $this->price->toArray(),
            'available_stock' => $this->availableStock,
        ];
    }
}
