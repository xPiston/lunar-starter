<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Cart\Port\CartGateway;
use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Checkout\Port\CheckoutGateway;
use App\Domain\Content\Port\HeroSlides;
use App\Infrastructure\Eloquent\Content\EloquentHeroSlides;
use App\Infrastructure\Lunar\Account\LunarOrderHistory;
use App\Infrastructure\Lunar\Cart\LunarCartGateway;
use App\Infrastructure\Lunar\Catalog\LunarProductCatalog;
use App\Infrastructure\Lunar\Checkout\LunarCheckoutGateway;
use Illuminate\Support\ServiceProvider;

/**
 * COMPOSITION ROOT: wires each PORT (domain interface) to the ADAPTER that
 * implements it. This is the ONLY file that knows about both the domain and
 * the infrastructure behind it.
 *
 * Replacing Lunar with another e-commerce engine happens HERE: write new
 * implementations of ProductCatalog/CartGateway/CheckoutGateway/OrderHistory
 * and change the bindings below. No other class in app/Domain,
 * app/Application or app/Http/Controllers/Storefront needs to change.
 */
final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductCatalog::class, LunarProductCatalog::class);
        $this->app->bind(CartGateway::class, LunarCartGateway::class);
        $this->app->bind(CheckoutGateway::class, LunarCheckoutGateway::class);
        $this->app->bind(OrderHistory::class, LunarOrderHistory::class);

        // Not every port is Lunar's: the homepage slider is the application's
        // own content, stored in its own table.
        $this->app->bind(HeroSlides::class, EloquentHeroSlides::class);
    }
}
