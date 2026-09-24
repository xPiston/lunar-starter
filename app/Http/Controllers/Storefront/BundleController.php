<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\BundleNotFoundException;
use App\Application\Catalog\ListBundles;
use App\Application\Catalog\ShowBundle;
use App\Domain\Catalog\Bundle;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BundleController extends Controller
{
    public function index(ListBundles $listBundles): Response
    {
        $bundles = $listBundles->handle();

        return Inertia::render('storefront/bundles/index', [
            'bundles' => array_map(
                static fn (Bundle $bundle): array => $bundle->toArray(),
                $bundles,
            ),
            'meta' => (new PageMeta(
                title: 'Bundles',
                description: 'Sets of our products sold together, for less than buying them one by one.',
            ))->toArray(),
        ]);
    }

    public function show(ShowBundle $showBundle, string $slug): Response
    {
        try {
            $bundle = $showBundle->handle($slug);
        } catch (BundleNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return Inertia::render('storefront/bundles/show', [
            'bundle' => $bundle->toArray(),
            'meta' => $this->meta($bundle)->toArray(),
        ]);
    }

    private function meta(Bundle $bundle): PageMeta
    {
        return new PageMeta(
            title: $bundle->name,
            description: PageMeta::excerpt($bundle->description),
            imageUrl: $bundle->imageUrl,
            type: 'product',
            jsonLd: [
                '@context' => 'https://schema.org',
                // A bundle is a set of products sold as one, which is exactly
                // what schema.org's ProductGroup... is not: that models
                // variants of one product. A Product with an offer is the
                // honest description of what is being sold here.
                '@type' => 'Product',
                'name' => $bundle->name,
                'description' => PageMeta::excerpt($bundle->description, 300),
                'url' => route('bundles.show', $bundle->slug),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format($bundle->price->minorAmount / 100, 2, '.', ''),
                    'priceCurrency' => $bundle->price->currencyCode,
                    'availability' => $bundle->availableStock === null || $bundle->availableStock > 0
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => route('bundles.show', $bundle->slug),
                ],
            ],
        );
    }
}
