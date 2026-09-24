<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListProductsByCollection;
use App\Domain\Catalog\ProductListing;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductListingRequest;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

final class CollectionController extends Controller
{
    public function __invoke(ProductListingRequest $request, ListProductsByCollection $listProducts, string $slug): Response
    {
        $query = $request->toQuery();
        $listing = $listProducts->handle($slug, $query);
        $name = ucwords(str_replace('-', ' ', $slug));

        return Inertia::render('storefront/collection', [
            'collectionSlug' => $slug,
            'listing' => $listing->toArray(),
            'filters' => $query->toArray(),
            'sortOptions' => ProductSort::options(),
            'meta' => $this->meta($name, $listing, $query)->toArray(),
        ]);
    }

    private function meta(string $name, ProductListing $listing, ProductQuery $query): PageMeta
    {
        $page = $listing->page > 1 ? " - page {$listing->page}" : '';

        return new PageMeta(
            title: $name.$page,
            description: "Browse our {$name} collection: {$listing->total} products in stock and ready to ship.",
            imageUrl: $listing->items[0]->thumbnailUrl ?? config('seo.image'),
            // Page 2 is worth indexing - it holds products nothing else links
            // to. A filtered or reordered page is the same catalogue sliced
            // differently, and every combination is a URL a crawler would
            // otherwise spend its budget on.
            noindex: $query->isFiltered(),
        );
    }
}
