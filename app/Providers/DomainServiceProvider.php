<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Cart\Port\CartGateway;
use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Checkout\Port\CheckoutGateway;
use App\Infrastructure\Lunar\Account\LunarOrderHistory;
use App\Infrastructure\Lunar\Cart\LunarCartGateway;
use App\Infrastructure\Lunar\Catalog\LunarProductCatalog;
use App\Infrastructure\Lunar\Checkout\LunarCheckoutGateway;
use Illuminate\Support\ServiceProvider;

/**
 * COMPOSITION ROOT: wires each PORT (domain interface) to its Lunar ADAPTER.
 * This is the ONLY file that knows about both the domain and Lunar.
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
    }
}
