<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

/**
 * How a listing is ordered. A closed set rather than a column name from the
 * query string: the storefront must never be able to order by an arbitrary
 * column, and the adapter decides what each of these means in its own
 * storage.
 */
enum ProductSort: string
{
    /** Most recently published first - the default everywhere. */
    case Newest = 'newest';

    case PriceLowToHigh = 'price_asc';

    case PriceHighToLow = 'price_desc';

    case NameAToZ = 'name';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::PriceLowToHigh => 'Price: low to high',
            self::PriceHighToLow => 'Price: high to low',
            self::NameAToZ => 'Name: A to Z',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $sort): array => ['value' => $sort->value, 'label' => $sort->label()],
            self::cases(),
        );
    }
}
