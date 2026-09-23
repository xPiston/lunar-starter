<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CartRecoveryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CollectionController;
use App\Http\Controllers\Storefront\ContentPageController;
use App\Http\Controllers\Storefront\GuestOrderLookupController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\NewsController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Seo\PageMeta;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/collections/{slug}', CollectionController::class)->name('collections.show');
Route::get('/products/{slug}', ProductController::class)->name('products.show');
Route::get('/search', SearchController::class)->name('search');

// Editorial content, authored in the back office. Two shapes of the same
// thing: /news is the dated, listed one, /pages/{slug} the standalone one.
// Both are prefixed rather than served from the root, so a page someone
// names "cart" or "search" can never shadow a real storefront route.
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/pages/{slug}', ContentPageController::class)->name('pages.show');

// Generated, not static files in public/: the sitemap follows the catalogue,
// and robots.txt needs the sitemap's absolute URL, which depends on APP_URL.
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');

// The two links an abandoned cart reminder carries. `signed` is what makes
// them safe to put in an email: the cart id and the email address are in the
// URL, so without a signature anyone could restore a stranger's cart by
// counting ids, or unsubscribe an address they don't own. Laravel answers 403
// on a tampered or expired signature.
Route::middleware('signed')->group(function (): void {
    // `{cartId}`, not `{cart}`: Lunar's ModelManifest registers a
    // `Route::model()` binding for every one of its models under the
    // camelCased class name, so a parameter called `cart` is resolved to a
    // `Lunar\Models\Cart` before the controller sees it. That would put Lunar
    // in a controller's signature, and it happens in the `web` group - before
    // `signed` runs - so probing ids would answer 404 or 403 depending on
    // whether the cart exists, which is an oracle this route shouldn't offer.
    Route::get('/cart/recover/{cartId}', [CartRecoveryController::class, 'recover'])
        ->whereNumber('cartId')
        ->name('cart.recover');
    Route::get('/cart/reminders/unsubscribe', [CartRecoveryController::class, 'unsubscribe'])
        ->name('cart.reminders.unsubscribe');
});
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::get('/checkout/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

// Static content, no use case behind it - a controller would be pure
// boilerplate here. See resources/js/pages/storefront/legal/* for the
// "this is a placeholder, not legal advice" notice shown on both pages.
Route::inertia('/terms', 'storefront/legal/terms', [
    'meta' => (new PageMeta(title: 'Terms of Service', description: 'The terms that apply to orders placed on this store.'))->toArray(),
])->name('legal.terms');
Route::inertia('/privacy', 'storefront/legal/privacy', [
    'meta' => (new PageMeta(title: 'Privacy Policy', description: 'What this store collects, why, and what your rights are.'))->toArray(),
])->name('legal.privacy');

// Scoped to "the current user's own orders" by LunarOrderHistory itself
// (see app/Domain/Account/Port/OrderHistory.php) - `auth` here only keeps
// guests out, it's not what prevents seeing someone else's order.
Route::middleware('auth')->prefix('account')->name('account.')->group(function (): void {
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{reference}', [AccountController::class, 'showOrder'])->name('orders.show');
});

// Guest checkout has no account to hold an order history, so this is the
// only way a guest can check on an order after leaving the confirmation
// page - reference + the email given at checkout, nothing else.
Route::get('/orders/lookup', [GuestOrderLookupController::class, 'show'])->name('orders.lookup');
Route::get('/orders/lookup/result', [GuestOrderLookupController::class, 'result'])->name('orders.lookup.result');
Route::post('/orders/lookup', [GuestOrderLookupController::class, 'find'])
    ->middleware('throttle:order-lookup')
    ->name('orders.lookup.find');

// Rate limiters registered in AppServiceProvider::boot(). Anonymous browsing
// (the routes above) isn't throttled - it carries no fraud/abuse risk beyond
// what any public page already has. State-changing storefront actions do.
Route::middleware('throttle:storefront-write')->group(function (): void {
    Route::post('/cart/lines', [CartController::class, 'store'])->name('cart.lines.store');
    Route::patch('/cart/lines/{line}', [CartController::class, 'update'])->name('cart.lines.update');
    Route::delete('/cart/lines/{line}', [CartController::class, 'destroy'])->name('cart.lines.destroy');
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.store');
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.destroy');

    Route::post('/checkout/address', [CheckoutController::class, 'updateAddress'])->name('checkout.address');
    Route::post('/checkout/shipping-option', [CheckoutController::class, 'selectShipping'])->name('checkout.shipping-option');
});

// Stricter, dedicated limiter: payment completion is the classic card-testing
// / carding abuse target (many stolen card numbers tried in quick
// succession). Per-IP throttling here is a first line of defense, not a full
// fraud solution on its own - Stripe Radar covers the sophisticated cases
// (rotating IPs, etc.).
Route::middleware('throttle:checkout-payment')->group(function (): void {
    Route::post('/checkout/complete', [CheckoutController::class, 'complete'])->name('checkout.complete');
    Route::get('/checkout/return', [CheckoutController::class, 'return'])->name('checkout.return');
});
