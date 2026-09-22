<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListCollections;
use App\Application\Catalog\ListFeaturedProducts;
use App\Application\Content\ListHeroSlides;
use App\Domain\Catalog\CollectionSummary;
use App\Domain\Catalog\ProductSummary;
use App\Domain\Content\HeroSlide;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(
        ListFeaturedProducts $listFeaturedProducts,
        ListCollections $listCollections,
        ListHeroSlides $listHeroSlides,
    ): Response {
        return Inertia::render('storefront/home', [
            'slides' => array_map(
                static fn (HeroSlide $slide): array => $slide->toArray(),
                $listHeroSlides->handle(),
            ),
            'products' => array_map(
                static fn (ProductSummary $product): array => $product->toArray(),
                $listFeaturedProducts->handle(),
            ),
            'collections' => array_map(
                static fn (CollectionSummary $collection): array => $collection->toArray(),
                $listCollections->handle(),
            ),
            'meta' => (new PageMeta(
                title: config('app.name').' - new arrivals',
                description: config('seo.description'),
                imageUrl: config('seo.image'),
            ))->toArray(),
        ]);
    }
}
