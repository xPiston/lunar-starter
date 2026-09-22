<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a route as rendering the storefront chrome (header + navbar), which
 * is what tells `HandleInertiaRequests` to attach `navCollections` to it.
 *
 * Applied to the whole storefront route group in routes/web.php rather than
 * matched on a list of route names, which would silently miss any route
 * added later.
 *
 * It's a pure marker - `HandleInertiaRequests` looks for this class in the
 * matched route's middleware (see `storefrontProps()`) instead of reading
 * state set here, because Inertia's own middleware calls `share()` from the
 * `web` group, i.e. before any route middleware has run. Setting a request
 * attribute here would therefore always be too late.
 *
 * Routing itself has already happened by then, so the marker is readable.
 * The alternative - calling `Inertia::share()` from here - keeps static
 * state for the lifetime of the process, which under a worker runtime
 * (Octane, FrankenPHP worker mode) leaks a storefront request's props into
 * the next, unrelated one.
 */
final class MarkStorefrontRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
