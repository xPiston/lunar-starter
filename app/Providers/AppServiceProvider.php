<?php

namespace App\Providers;

use App\Filament\Resources\ContentPageResource;
use App\Filament\Resources\HeroSlideResource;
use Filament\Panel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
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
                ->resources([HeroSlideResource::class, ContentPageResource::class])
            )
            ->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
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
