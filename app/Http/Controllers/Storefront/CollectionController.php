<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListProductsByCollection;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

final class CollectionController extends Controller
{
    public function __invoke(ListProductsByCollection $listProducts, string $slug): Response
    {
        $products = $listProducts->handle($slug);
        $name = ucwords(str_replace('-', ' ', $slug));

        return Inertia::render('storefront/collection', [
            'collectionSlug' => $slug,
            'products' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $products,
            ),
            'meta' => (new PageMeta(
                title: $name,
                description: "Browse our {$name} collection: ".count($products).' products in stock and ready to ship.',
                imageUrl: $products[0]->thumbnailUrl ?? config('seo.image'),
            ))->toArray(),
        ]);
    }
}
