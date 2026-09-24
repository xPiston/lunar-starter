<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * One page of a listing, and enough about the rest to navigate it.
 *
 * `total` is the count across every page, not of `items` - without it the
 * storefront cannot tell "24 products" from "24 of 300", and there is no
 * last page to link to.
 */
final readonly class ProductListing
{
    /**
     * @param  ProductSummary[]  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public static function empty(ProductQuery $query): self
    {
        return new self([], 0, $query->page, $query->perPage);
    }

    /**
     * At least 1: an empty catalogue still has a page to be on, and a last
     * page of 0 would render a pager that links nowhere.
     */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $this->items,
            ),
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'last_page' => $this->lastPage(),
        ];
    }
}
