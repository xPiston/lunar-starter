<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Shared\Money;

/**
 * Several products sold together at their own price.
 *
 * `price` is the bundle's, and `itemsTotal` what the same contents cost
 * bought separately: the difference between the two is the offer, and a
 * storefront that shows one without the other is asking to be taken on trust.
 *
 * `availableStock` follows the scarcest part - null meaning nothing in the
 * bundle limits it.
 */
final readonly class Bundle
{
    /**
     * @param  BundleItem[]  $items
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $imageUrl,
        public array $items,
        public Money $price,
        public Money $itemsTotal,
        /**
         * The difference, formatted by the adapter rather than worked out
         * here: a Money carries a display string, and how an amount reads in
         * a currency is not something the domain knows.
         */
        public Money $savings,
        public ?int $availableStock,
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
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'items' => array_map(
                static fn (BundleItem $item): array => $item->toArray(),
                $this->items,
            ),
            'price' => $this->price->toArray(),
            'items_total' => $this->itemsTotal->toArray(),
            'savings' => $this->savings->toArray(),
            'available_stock' => $this->availableStock,
        ];
    }
}
