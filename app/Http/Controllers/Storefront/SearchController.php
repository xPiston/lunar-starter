<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\SearchProducts;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductListingRequest;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    public function __invoke(ProductListingRequest $request, SearchProducts $searchProducts): Response
    {
        $term = trim((string) $request->query('q', ''));
        $query = $request->toQuery();

        $listing = $term === ''
            ? ProductListing::empty($query)
            : $searchProducts->handle($term, $query);

        return Inertia::render('storefront/search', [
            'query' => $term,
            'listing' => $listing->toArray(),
            'filters' => $query->toArray(),
            'sortOptions' => ProductSort::options(),
            // Search result pages are the textbook case for noindex: infinite
            // URL variations, thin duplicated content, no value in an index.
            'meta' => (new PageMeta(
                title: $term === '' ? 'Search' : "Search: {$term}",
                description: 'Search the catalogue.',
                noindex: true,
            ))->toArray(),
        ]);
    }
}
