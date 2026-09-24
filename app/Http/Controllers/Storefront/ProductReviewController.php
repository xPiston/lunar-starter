<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ProductNotFoundException;
use App\Application\Catalog\ShowProduct;
use App\Application\Review\AlreadyReviewedException;
use App\Application\Review\SubmitProductReview;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Writing a review.
 *
 * Behind `auth`: a review carries a name and a "verified purchase" badge,
 * both of which need an identity to mean anything, and an anonymous form is a
 * spam target that would need moderation tooling this template doesn't ship.
 * Nothing written here is visible until a member of staff approves it.
 */
final class ProductReviewController extends Controller
{
    public function store(
        Request $request,
        ShowProduct $showProduct,
        SubmitProductReview $submitReview,
        string $slug,
    ): RedirectResponse {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $product = $showProduct->handle($slug);
        } catch (ProductNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        try {
            $submitReview->handle(
                productId: $product->id,
                userId: (int) $request->user()->id,
                rating: (int) $validated['rating'],
                body: (string) $validated['body'],
            );
        } catch (AlreadyReviewedException $exception) {
            // A validation error rather than a 409: it renders next to the
            // form the visitor just used, which is where they need to read it.
            throw ValidationException::withMessages(['body' => $exception->getMessage()]);
        }

        return back()->with('success', 'Thanks - your review will appear once it has been checked.');
    }
}
