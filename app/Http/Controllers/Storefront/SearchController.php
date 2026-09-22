<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\SearchProducts;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
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
            // Search result pages are the textbook case for noindex: infinite
            // URL variations, thin duplicated content, no value in an index.
            'meta' => (new PageMeta(
                title: $query === '' ? 'Search' : "Search: {$query}",
                description: 'Search the catalogue.',
                noindex: true,
            ))->toArray(),
        ]);
    }
}
