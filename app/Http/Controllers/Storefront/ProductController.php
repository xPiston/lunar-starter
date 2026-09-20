<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListFeaturedProducts;
use App\Application\Catalog\ProductNotFoundException;
use App\Application\Catalog\ShowProduct;
use App\Domain\Catalog\ProductSummary;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController extends Controller
{
    public function __invoke(ShowProduct $showProduct, string $slug, ListFeaturedProducts $listFeaturedProducts): Response
    {
        try {
            $product = $showProduct->handle($slug);
        } catch (ProductNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        // Reuses the same "recently published" query as the homepage - no
        // dedicated "related products" concept in the domain, and this is
        // simple enough not to need one.
        $relatedProducts = array_values(array_filter(
            $listFeaturedProducts->handle(5),
            static fn (ProductSummary $candidate): bool => $candidate->id !== $product->id,
        ));

        return Inertia::render('storefront/product', [
            'product' => $product->toArray(),
            'relatedProducts' => array_map(
                static fn (ProductSummary $candidate): array => $candidate->toArray(),
                array_slice($relatedProducts, 0, 4),
            ),
        ]);
    }
}
