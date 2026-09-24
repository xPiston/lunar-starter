<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListFeaturedProducts;
use App\Application\Catalog\ProductNotFoundException;
use App\Application\Catalog\ShowProduct;
use App\Application\Review\CanReviewProduct;
use App\Application\Review\GetProductRating;
use App\Application\Review\ListProductReviews;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductSummary;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Review\RatingSummary;
use App\Domain\Review\Review;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController extends Controller
{
    public function __invoke(
        ShowProduct $showProduct,
        string $slug,
        ListFeaturedProducts $listFeaturedProducts,
        ListProductReviews $listReviews,
        GetProductRating $getRating,
        CanReviewProduct $canReview,
        Request $request,
    ): Response {
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

        $rating = $getRating->handle($product->id);
        $reviews = $listReviews->handle($product->id);

        return Inertia::render('storefront/product', [
            'product' => $product->toArray(),
            'rating' => $rating->toArray(),
            'reviews' => array_map(
                static fn (Review $review): array => $review->toArray(),
                $reviews,
            ),
            // Drives which of "write a review", "sign in to review" or
            // "you've reviewed this" the page offers. Resolved here rather
            // than in React, which has no way to know.
            'canReview' => $canReview->handle($product->id, $request->user()?->id),
            'relatedProducts' => array_map(
                static fn (ProductSummary $candidate): array => $candidate->toArray(),
                array_slice($relatedProducts, 0, 4),
            ),
            'meta' => $this->meta($product, $rating)->toArray(),
        ]);
    }

    private function meta(Product $product, RatingSummary $rating): PageMeta
    {
        $cheapest = $product->variants[0] ?? null;
        $inStock = array_filter(
            $product->variants,
            static fn (ProductVariant $variant): bool => $variant->availableStock === null || $variant->availableStock > 0,
        );

        // Full-size image, not the thumbnail: link previews render around
        // 1200x630 and would upscale a 200px conversion into mush.
        $shareImage = $product->images[0] ?? $product->thumbnailUrl;

        return new PageMeta(
            title: $product->name,
            description: PageMeta::excerpt($product->description),
            imageUrl: $shareImage,
            type: 'product',
            // The payload Google needs to show price, availability and stars
            // next to the result instead of a plain link. Null entries are
            // stripped rather than emitted: a key present with a null value is
            // invalid structured data, and one bad key invalidates the block -
            // taking the price snippet down with it.
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => PageMeta::excerpt($product->description, 300),
                'image' => array_values(array_filter([...$product->images, $product->thumbnailUrl])),
                'url' => route('products.show', $product->slug),
                // Only once there is something to aggregate. An
                // aggregateRating with a count of zero is invalid structured
                // data, and Google rejects the whole block over it - so a
                // product with no reviews yet would lose its price snippet
                // too.
                'aggregateRating' => $rating->hasReviews() ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round($rating->average, 1),
                    'reviewCount' => $rating->count,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ] : null,
                'offers' => $cheapest === null ? null : [
                    '@type' => 'Offer',
                    'price' => number_format($cheapest->price->minorAmount / 100, 2, '.', ''),
                    'priceCurrency' => $cheapest->price->currencyCode,
                    'availability' => $inStock === []
                        ? 'https://schema.org/OutOfStock'
                        : 'https://schema.org/InStock',
                    'url' => route('products.show', $product->slug),
                ],
            ], static fn (mixed $value): bool => $value !== null),
            ogProperties: $cheapest === null ? [] : [
                'product:price:amount' => number_format($cheapest->price->minorAmount / 100, 2, '.', ''),
                'product:price:currency' => $cheapest->price->currencyCode,
                'product:availability' => $inStock === [] ? 'out of stock' : 'in stock',
            ],
        );
    }
}
