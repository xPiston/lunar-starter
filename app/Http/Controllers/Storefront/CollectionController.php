<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListProductsByCollection;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class CollectionController extends Controller
{
    public function __invoke(ListProductsByCollection $listProducts, string $slug): Response
    {
        return Inertia::render('storefront/collection', [
            'collectionSlug' => $slug,
            'products' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $listProducts->handle($slug),
            ),
        ]);
    }
}
