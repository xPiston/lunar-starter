# Architecture

How this template is put together, and how far the hexagonal pattern is
actually taken. Back to the [README](../README.md).

## Why hexagonal, and how far

Lunar is an excellent engine (catalog, contextual pricing, taxes, cart), but
coupling all application code directly to its Eloquent models and facades
would make switching engines later (migrating to another package, to an
external API, etc.) very costly.

The choice made here: **Lunar as the engine behind thin ports**, not
"rewrite the whole e-commerce domain by hand". The domain doesn't reinvent
what Lunar already does (price, tax, discount calculation); it just defines
contracts (interfaces) and value objects that expose no Lunar detail to the
rest of the application.

Strict rule: **`Lunar\*` must never be imported outside of
`app/Infrastructure/Lunar/**`.** Check this at any time with:

```sh
grep -rn "use Lunar\\\\" app/Domain app/Application
```

(should return nothing — the only occurrences of the word "Lunar" elsewhere
are comment mentions explaining *why* this decoupling exists).

The **Filament** admin panel shipped by Lunar is kept as-is, not wrapped:
it's Lunar's own tool, not a feature this template maintains. Available by
default at `/lunar`, and **two-factor authentication is required** for every
staff account (`AppServiceProvider::register()` calls
`LunarPanel::forceTwoFactorAuth()`) — Lunar's `Staff` model already ships
fully wired for TOTP app authentication with recovery codes
(`Filament\Auth\MultiFactor\App\AppAuthentication`), this just switches it
from opt-in to mandatory. A staff account with no authenticator app set up
yet is redirected to a forced setup page instead of reaching the dashboard —
`tests/Feature/Admin/TwoFactorAuthenticationTest.php` proves this against a
real Staff record, not just that the option is turned on somewhere.

## Anatomy of the layers

```
app/Domain/                     Zero Lunar import, zero Laravel/Illuminate import.
├── Shared/Money.php              Immutable VO (minorAmount, currencyCode, formatted).
├── Catalog/                      Product, ProductSummary, ProductVariant, CollectionSummary
│   └── Port/ProductCatalog.php   Interface: listFeatured, listByCollection, findBySlug, listCollections.
├── Cart/                         Cart, CartLine
│   └── Port/CartGateway.php      Interface: current, addLine, updateLine, removeLine.
└── Checkout/                     Address, Country, ShippingOption, Order, OrderLine, PaymentIntent, CheckoutState
    └── Port/CheckoutGateway.php  Interface: addresses, shipping, payment, order completion.

app/Application/                Use cases = 1 class = 1 handle() method, injected with the port.
├── Catalog/                      ListFeaturedProducts, ListProductsByCollection, ShowProduct, ListCollections
├── Cart/                         ViewCart, AddProductToCart, UpdateCartLineQuantity, RemoveCartLine
└── Checkout/                     ShowCheckout, SetBillingAddress, SetShippingAddress, SelectShippingOption, StartPayment, CompleteCheckout

app/Infrastructure/Lunar/       ONLY place allowed to import Lunar\*/Lunar\Stripe\*.
├── Support/MoneyMapper.php       Lunar\DataTypes\Price -> Domain\Shared\Money.
├── Catalog/                      LunarProductCatalog implements ProductCatalog (+ ProductMapper)
├── Cart/                         LunarCartGateway implements CartGateway (+ CartMapper)
└── Checkout/                     LunarCheckoutGateway implements CheckoutGateway (+ AddressMapper, ShippingOptionMapper, OrderMapper)

app/Providers/DomainServiceProvider.php   Composition root: bind(ProductCatalog::class, LunarProductCatalog::class), etc.

app/Http/Controllers/Storefront/        Standard Laravel inbound adapter, injects use cases (never the ports or Lunar directly).
resources/js/pages/storefront/          Inertia/React pages consuming exactly the domain VOs' toArray().
```

Each domain VO exposes a `toArray()` method that produces the exact JSON
shape consumed by the frontend (mirrored in
`resources/js/types/storefront.ts`) — no extra DTO/Resource layer, the VO
itself acts as the contract.

### Honesty of the pattern

The `Application/*` handlers are deliberately thin: Lunar already carries
the price/tax/cart logic, so these classes just orchestrate port -> VO.
Their value isn't business sophistication, it's the **replacement boundary**
they embody.

## Replacing Lunar with something else

Since the rest of the application only knows `ProductCatalog`, `CartGateway`
and `CheckoutGateway` (the ports), replacing Lunar with another engine
(another package, an external API, an in-house CRM...) takes 3 steps,
without touching the domain, the use cases, the controllers, or the
frontend:

1. Write a new adapter, e.g. `app/Infrastructure/Acme/Catalog/AcmeProductCatalog.php implements ProductCatalog` (and its equivalent for `CartGateway`/`CheckoutGateway`), translating calls from the new engine into the `Domain\Catalog\*` / `Domain\Cart\*` / `Domain\Checkout\*` VOs.
2. Rewire the bindings in `app/Providers/DomainServiceProvider.php`:
   ```php
   $this->app->bind(ProductCatalog::class, AcmeProductCatalog::class);
   $this->app->bind(CartGateway::class, AcmeCartGateway::class);
   $this->app->bind(CheckoutGateway::class, AcmeCheckoutGateway::class);
   ```
3. Remove `lunarphp/lunar`/`lunarphp/stripe` from `composer.json` once the
   old `app/Infrastructure/Lunar/**` adapter is deleted.

Nothing in `app/Domain`, `app/Application`, `app/Http/Controllers/Storefront`
or `resources/js` needs to change.

