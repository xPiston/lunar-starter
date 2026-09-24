<?php

namespace App\Providers;

use App\Filament\Resources\ContentPageResource;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Resources\ProductBundleResource;
use App\Filament\Resources\ProductReviewResource;
use App\Infrastructure\Lunar\Checkout\OrderStatusObserver;
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

        // A cart or order line stores its purchasable as a morph. Without an
        // alias that column holds a fully-qualified class name, and renaming
        // or moving the model would orphan every line ever sold.
        // morphMap, not enforceMorphMap: the latter also demands an entry for
        // every other morphed model in the application - including Laravel's
        // own User - and throws on the first one that has none.
        Relation::morphMap([
            'bundle' => ProductBundle::class,
        ]);

        // What to eager load per kind of purchasable. Lunar's published
        // config lists `taxClass`, `values` and `product`, which only exist
        // on a product variant - a cart holding a bundle would throw
        // RelationNotFoundException before rendering. `morphWith` is the
        // Eloquent answer, and it needs a closure, which is why this cannot
        // live in a config file that has to survive `config:cache`.
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
