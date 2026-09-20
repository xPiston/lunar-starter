<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\SearchProducts;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    public function __invoke(Request $request, SearchProducts $searchProducts): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('storefront/search', [
            'query' => $query,
            'products' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $query !== '' ? $searchProducts->handle($query) : [],
            ),
        ]);
    }
}
