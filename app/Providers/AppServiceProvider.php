<?php

namespace App\Providers;

use App\Filament\Resources\ContentPageResource;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Resources\ProductBundleResource;
use App\Filament\Resources\ProductReviewResource;
use App\Infrastructure\Lunar\Checkout\OrderStatusObserver;
use App\Infrastructure\Lunar\Checkout\OrderStockObserver;
use App\Models\ProductBundle;
use Filament\Panel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Order as LunarOrder;
use Lunar\Models\ProductVariant;
use Lunar\Shipping\ShippingPlugin;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lunar's Staff model already ships fully wired for TOTP app
        // authentication (Filament\Auth\MultiFactor\App\AppAuthentication,
        // recovery codes included) - forceTwoFactorAuth() just switches it
        // from "available, opt-in" to "required before the dashboard is
        // reachable". Read at the exact moment register() builds the panel,
        // so this call must happen first.
        //
        // ->panel() adds lunarphp/table-rate-shipping's admin screens
        // (shipping zones/methods/rates) to the same panel - it doesn't
        // replace Lunar's own plugins() call, Filament merges them.
        //
        // resources() appends to the panel's list rather than replacing it,
        // and the closure runs after Lunar has built the panel - so this is
        // how the application's own screens join the same admin. HeroSlide is
        // not a Lunar model; nothing about it goes through Lunar.
        LunarPanel::forceTwoFactorAuth()
            ->panel(fn (Panel $panel) => $panel
                ->plugin(new ShippingPlugin)
                ->resources([
                    HeroSlideResource::class,
                    ContentPageResource::class,
                    ProductReviewResource::class,
                    ProductBundleResource::class,
                ])
            )
            ->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Lunar fires no event when an order changes status, so this watches
        // the model itself - which also catches a change made by a command or
        // a webhook, not just one made in the admin panel. Registered on the
        // resolved model class because Lunar lets a project swap it.
        LunarOrder::observe(OrderStatusObserver::class);

        // Lunar manages no inventory at all - it reduces nothing when an
        // order is placed and returns nothing when one is cancelled. See
        // App\Infrastructure\Lunar\Inventory\LunarStockLedger.
        LunarOrder::observe(OrderStockObserver::class);

        // A cart or order line stores its purchasable as a morph. Without an
        // alias that column holds a fully-qualified class name, and renaming
        // or moving the model would orphan every line ever sold.
        // morphMap, not enforceMorphMap: the latter also demands an entry for
        // every other morphed model in the application - including Laravel's
        // own User - and throws on the first one that has none.
        Relation::morphMap([
            'bundle' => ProductBundle::class,
        ]);

        $this->eagerLoadPurchasables();
    }

    /**
     * What to eager load per kind of purchasable.
     *
     * Lunar's published config lists `taxClass`, `values` and `product`,
     * which only exist on a product variant - a cart holding a bundle would
     * throw RelationNotFoundException before rendering. `morphWith` is the
     * Eloquent answer, and it needs a closure, which is why this cannot live
     * in a config file.
     *
     * It cannot be written here unguarded either. `config:cache` boots the
     * providers and then serialises the whole config repository with
     * `var_export`, so a closure put into the config by boot() ends up in the
     * file it writes - and a closure has no PHP source form. The command dies
     * on it ("Call to undefined method Closure::__set_state"), and because
     * frankenphp/docker-entrypoint.sh runs `config:cache` on startup, the
     * production container dies with it.
     *
     * Skipping those two commands costs nothing: boot() runs on every request
     * and every queue job whether the config is cached or not, so the cached
     * file carries the string relations and this closure is laid on top of it
     * each time. `tests/Feature/ConfigCacheTest.php` is what keeps it that
     * way.
     */
    private function eagerLoadPurchasables(): void
    {
        if ($this->cachingConfiguration()) {
            return;
        }

        config(['lunar.cart.eager_load' => array_merge(
            (array) config('lunar.cart.eager_load', []),
            [
                'lines.purchasable' => fn (MorphTo $purchasable) => $purchasable->morphWith([
                    ProductVariant::class => ['taxClass', 'values', 'product.thumbnail'],
                    ProductBundle::class => ['items.variant.product'],
                ]),
            ],
        )]);
    }

    /**
     * Whether this process is on its way to writing the config cache.
     *
     * Searches the whole command line rather than using
     * `runningConsoleCommand()`, which only looks at the first argument:
     * `php artisan --no-interaction config:cache` would walk straight past
     * that check and take the container down with it.
     */
    private function cachingConfiguration(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'] ?? [];

        return array_intersect(['config:cache', 'optimize'], $argv) !== [];
    }

    /**
     * Named limiters applied to storefront routes in routes/storefront.php.
     * Keyed by IP: the storefront has no authenticated "customer" concept of
     * its own (Lunar carts are guest-friendly by design), so IP is the only
     * identity available for anonymous shoppers.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'storefront-write',
            fn (Request $request) => Limit::perMinute(30)->by($request->ip()),
        );

        RateLimiter::for(
            'checkout-payment',
            fn (Request $request) => Limit::perMinute(10)->by($request->ip()),
        );

        // Guest order lookup is a two-field guessing surface (reference +
        // email): as strict as payment completion for the same reason.
        RateLimiter::for(
            'order-lookup',
            fn (Request $request) => Limit::perMinute(10)->by($request->ip()),
        );
    }
}
