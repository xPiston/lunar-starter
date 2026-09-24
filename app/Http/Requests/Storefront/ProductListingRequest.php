<?php

declare(strict_types=1);

namespace App\Http\Requests\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turns the query string of a listing page into a domain ProductQuery.
 *
 * Validated rather than cast, because every value here reaches a database
 * query: `sort` is checked against the enum so no column name can be smuggled
 * in, and `per_page` is capped so a crawler cannot ask for the whole
 * catalogue in one request.
 *
 * Prices are entered by a visitor in whole units and held in the domain as
 * minor units, which is where the conversion belongs - the Http layer speaks
 * the visitor's language, the domain speaks cents.
 */
final class ProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ProductQuery::MAX_PER_PAGE],
            'sort' => ['sometimes', Rule::enum(ProductSort::class)],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'in_stock' => ['sometimes', 'boolean'],
        ];
    }

    public function toQuery(): ProductQuery
    {
        return new ProductQuery(
            page: (int) $this->integer('page', 1),
            perPage: (int) $this->integer('per_page', ProductQuery::PER_PAGE),
            sort: $this->enum('sort', ProductSort::class) ?? ProductSort::Newest,
            minPrice: $this->toMinorUnits('min_price'),
            maxPrice: $this->toMinorUnits('max_price'),
            inStockOnly: $this->boolean('in_stock'),
        );
    }

    private function toMinorUnits(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || $value === ''
            ? null
            : (int) round(((float) $value) * 100);
    }
}
