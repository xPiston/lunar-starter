<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListCollections;
use App\Application\Catalog\ListFeaturedProducts;
use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(ListFeaturedProducts $listFeaturedProducts, ListCollections $listCollections): Response
    {
        return Inertia::render('storefront/home', [
            'products' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $listFeaturedProducts->handle(),
            ),
            'collections' => array_map(
                static fn (CollectionSummary $collection): array => $collection->toArray(),
                $listCollections->handle(),
            ),
        ]);
    }
}
