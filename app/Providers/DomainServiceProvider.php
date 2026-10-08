<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Cart\Port\CartGateway;
use App\Domain\Cart\Port\CartReminders;
use App\Domain\Catalog\Port\BundleCatalog;
use App\Domain\Catalog\Port\ProductCatalog;
use App\Domain\Checkout\Port\CheckoutGateway;
use App\Domain\Content\Port\ContentPages;
use App\Domain\Content\Port\HeroSlides;
use App\Domain\Erp\Port\ErpGateway;
use App\Domain\Inventory\Port\StockLedger;
use App\Domain\Review\Port\ProductReviews;
use App\Domain\Review\Port\PurchaseCheck;
use App\Infrastructure\Eloquent\Catalog\EloquentBundleCatalog;
use App\Infrastructure\Eloquent\Content\EloquentContentPages;
use App\Infrastructure\Eloquent\Content\EloquentHeroSlides;
use App\Infrastructure\Eloquent\Review\EloquentProductReviews;
use App\Infrastructure\Erp\Dolibarr\DolibarrClient;
use App\Infrastructure\Erp\Dolibarr\DolibarrErpGateway;
use App\Infrastructure\Erp\NullErpGateway;
use App\Infrastructure\Erp\Odoo\OdooClient;
use App\Infrastructure\Erp\Odoo\OdooErpGateway;
use App\Infrastructure\Lunar\Account\LunarOrderHistory;
use App\Infrastructure\Lunar\Cart\LunarCartGateway;
use App\Infrastructure\Lunar\Cart\LunarCartReminders;
use App\Infrastructure\Lunar\Catalog\LunarProductCatalog;
use App\Infrastructure\Lunar\Checkout\LunarCheckoutGateway;
use App\Infrastructure\Lunar\Inventory\LunarStockLedger;
use App\Infrastructure\Lunar\Review\LunarPurchaseCheck;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
        $this->app->bind(CartReminders::class, LunarCartReminders::class);
        $this->app->bind(CheckoutGateway::class, LunarCheckoutGateway::class);
        $this->app->bind(OrderHistory::class, LunarOrderHistory::class);

        // Not every port is Lunar's: the slider and the editorial pages are
        // the application's own content, stored in its own tables.
        $this->app->bind(HeroSlides::class, EloquentHeroSlides::class);
        $this->app->bind(ContentPages::class, EloquentContentPages::class);
        $this->app->bind(ProductReviews::class, EloquentProductReviews::class);

        // Bundles are the application's own offer built out of Lunar's
        // products, so the adapter is ours even though it prices through
        // Lunar.
        $this->app->bind(BundleCatalog::class, EloquentBundleCatalog::class);

        // The one context wired to both: reviews are ours, the purchase they
        // claim to be based on is Lunar's.
        $this->app->bind(PurchaseCheck::class, LunarPurchaseCheck::class);

        // Lunar manages no inventory of its own: nothing in its core reduces
        // the stock column when an order is placed. This binding is what
        // makes "Only 2 left" mean anything after the second sale.
        $this->app->bind(StockLedger::class, LunarStockLedger::class);

        // The only port whose adapter is chosen at runtime rather than fixed
        // here: which ERP a shop runs - or whether it runs one at all - is a
        // deployment fact, not a code fact.
        $this->app->bind(ErpGateway::class, $this->erpGateway(...));
    }

    /**
     * Builds the ERP adapter named by `config('erp.driver')`.
     *
     * `none` is the default and binds a gateway that does nothing, which is
     * what makes the whole feature optional without a single conditional
     * anywhere else: callers push every paid order the same way, and a shop
     * with no ERP has nothing to switch off.
     *
     * An unknown driver throws rather than falling back to `none`. Silently
     * doing nothing because `ERP_DRIVER=oddo` is a typo is the kind of failure
     * nobody notices until an accountant asks where three weeks of orders went.
     */
    private function erpGateway(): ErpGateway
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('erp');
        $timeout = (int) ($config['timeout_seconds'] ?? 30);

        return match ($driver = (string) ($config['driver'] ?? 'none')) {
            'none' => new NullErpGateway,
            'odoo' => new OdooErpGateway(
                new OdooClient(
                    url: (string) config('erp.odoo.url'),
                    database: (string) config('erp.odoo.database'),
                    username: (string) config('erp.odoo.username'),
                    apiKey: (string) config('erp.odoo.api_key'),
                    timeout: $timeout,
                ),
                confirmOrders: (bool) config('erp.odoo.confirm_orders'),
            ),
            'dolibarr' => new DolibarrErpGateway(
                new DolibarrClient(
                    url: (string) config('erp.dolibarr.url'),
                    apiKey: (string) config('erp.dolibarr.api_key'),
                    timeout: $timeout,
                ),
                validateOrders: (bool) config('erp.dolibarr.validate_orders'),
            ),
            default => throw new InvalidArgumentException(
                "Unknown ERP driver [{$driver}]. Supported: none, odoo, dolibarr."
            ),
        };
    }
}
