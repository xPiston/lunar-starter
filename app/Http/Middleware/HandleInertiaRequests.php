<?php

namespace App\Http\Middleware;

use App\Application\Cart\CountCartItems;
use App\Application\Catalog\ListCollections;
use App\Domain\Catalog\CollectionSummary;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // Drives the storefront header's cart badge. Shared globally
            // rather than passed page by page so every response (including
            // the redirect back from "add to cart") refreshes it; safe to
            // do because CountCartItems never creates a cart and costs no
            // query at all for a visitor who hasn't started one - see
            // App\Domain\Cart\Port\CartGateway::currentItemCount().
            'cartItemCount' => app(CountCartItems::class)->handle(),
            'navCollections' => fn (): array => $this->navCollections($request),
        ]);
    }

    /**
     * Collection links for the storefront navbar - empty everywhere else, so
     * the dashboard, settings and auth pages never run the query behind them.
     *
     * The key is always present rather than conditionally added: Inertia's
     * shared props accumulate on a singleton, so a key added on one request
     * would still be hanging around on the next one under a worker runtime
     * (Octane, FrankenPHP worker mode). What actually matters - not paying
     * for the query off the storefront - is handled by this closure instead,
     * which Inertia only calls when it renders a page (so not on the
     * redirects the cart/checkout POSTs return either).
     *
     * Storefront-ness is read from the matched route's middleware, not from
     * request state, because Inertia calls share() from the `web` group -
     * before any route middleware has run (see MarkStorefrontRequest).
     *
     * @return array<int, array<string, mixed>>
     */
    private function navCollections(Request $request): array
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        if (! in_array(MarkStorefrontRequest::class, $middleware, true)) {
            return [];
        }

        return array_map(
            static fn (CollectionSummary $collection): array => $collection->toArray(),
            app(ListCollections::class)->handle(),
        );
    }
}
