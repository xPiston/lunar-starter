<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * One part of a bundle, as the storefront shows it: what it is, how many go
 * in, where to read about it, and what it looks like.
 */
final readonly class BundleItem
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $productSlug,
        public ?string $imageUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'product_slug' => $this->productSlug,
            'image_url' => $this->imageUrl,
        ];
    }
}
