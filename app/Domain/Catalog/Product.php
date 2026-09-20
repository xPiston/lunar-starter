<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * Full product sheet (detail page). The domain only knows what the
 * storefront needs to display: no raw Lunar attribute, no Eloquent model.
 */
final readonly class Product
{
    /**
     * @param  string[]  $images
     * @param  ProductVariant[]  $variants
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $thumbnailUrl,
        public array $images,
        public array $variants,
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
            'thumbnail_url' => $this->thumbnailUrl,
            'images' => $this->images,
            'variants' => array_map(
                static fn (ProductVariant $variant): array => $variant->toArray(),
                $this->variants,
            ),
        ];
    }
}
