<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * Everything a visitor can ask of a listing: which page, in what order, and
 * narrowed how.
 *
 * One object rather than a growing parameter list, so adding a filter later
 * doesn't change the signature of every port method, use case and adapter
 * that carries it.
 *
 * Prices are minor units (cents), like every other amount in the domain.
 */
final readonly class ProductQuery
{
    public const PER_PAGE = 24;

    public const MAX_PER_PAGE = 96;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::PER_PAGE,
        public ProductSort $sort = ProductSort::Newest,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public bool $inStockOnly = false,
    ) {}

    /**
     * Whether the visitor narrowed or reordered anything.
     *
     * Drives `noindex` on the page: a plain page 2 is worth crawling, but
     * every combination of filters is the same catalogue sliced differently,
     * and letting a crawler enumerate them burns its budget on duplicates.
     */
    public function isFiltered(): bool
    {
        return $this->sort !== ProductSort::Newest
            || $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->inStockOnly;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * The query as the storefront needs to echo it back - to keep a filter
     * bar in sync, and to build page links that preserve what was chosen.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sort' => $this->sort->value,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'in_stock_only' => $this->inStockOnly,
        ];
    }
}
