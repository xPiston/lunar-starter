# E-commerce Hexa Template

Laravel e-commerce store template built with **hexagonal architecture**
(ports & adapters), using [LunarPHP](https://lunarphp.io) as the engine
(catalog, cart, pricing, taxes) and [Inertia.js](https://inertiajs.com) + React
for the frontend. Laravel 12, PHP 8.4.

The goal isn't to cover 100% of e-commerce features, but to correctly lay
down a reusable pattern — across three contexts (Catalog, Cart, Checkout with
real Stripe payment) — to serve as a starting point for a real project.

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

## The Checkout flow (address -> shipping -> Stripe payment -> order)

Checkout follows exactly the same Domain -> Application ->
Infrastructure/Lunar -> Http -> Frontend recipe as Catalog/Cart, with
[lunarphp/stripe](https://github.com/lunarphp/stripe) as the only payment
integration point:

- **Address**: a single frontend step (billing + "same for shipping"
  checked by default) that posts to `POST /checkout/address`
  (`SetBillingAddress`/`SetShippingAddress`).
- **Shipping**: `POST /checkout/shipping-option`. Real, admin-configurable
  rates via [`lunarphp/table-rate-shipping`](https://github.com/lunarphp/table-rate-shipping)
  (its own `Lunar\Shipping\ShippingModifier` auto-registers itself, nothing
  to wire on our side) — zones (by country/state/postcode, or unrestricted),
  methods (flat rate, weight-tiered "ship by", free, collection), and rates
  are all managed at `/lunar/shipping-zones` and `/lunar/shipping-methods`,
  no code required to add or change one. `database/seeders/ShippingDefaultsSeeder.php`
  seeds a demo "Worldwide" zone with two flat-rate methods (Standard $5,
  Express $15) so the template works out of the box; a real store replaces
  or extends this entirely through the admin panel.
- **Tax**: fully built into Lunar core, but `lunar:install` deliberately
  creates no actual rate — the percentage is business-specific (VAT, sales
  tax, GST...) and the framework leaves it to the store. Without one, tax is
  silently 0% forever, no error, easy to miss.
  `database/seeders/TaxDefaultsSeeder.php` adds a demo 20% "Standard Rate"
  (UK/EU VAT) on the default zone/class so cart/checkout totals are real out
  of the box; a real store sets its actual rate(s) at `/lunar/tax-rates`,
  often several across additional tax zones for different countries/states.
- **Payment**: the `/checkout` page mounts a Stripe Payment Element
  (`@stripe/react-stripe-js`) once the address and shipping are filled in
  (`ShowCheckout` then creates a PaymentIntent via
  `Lunar\Stripe\Facades\Stripe::fetchOrCreateIntent()`). Client-side
  confirmation uses `redirect: 'if_required'`: the common case (card)
  resolves in JS and posts to `POST /checkout/complete`; payment methods
  that actually redirect fall back to `GET /checkout/return`. Both routes
  call the same `CompleteCheckout` use case, which delegates to
  `Lunar\Facades\Payments::driver('stripe')->cart($cart)->withData([...])->authorize()`
  — it's Lunar/Stripe that creates the order (`Cart::createOrder()`
  internally), never our Application layer directly.
- **Confirmation**: `GET /checkout/confirmation` reads **only** from the
  session (`checkout.last_order`, written right after a successful payment)
  — never an id/reference from the URL, to avoid any order enumeration
  without having to force authentication (guest checkout works).

To extend the pattern to a **new** context (e.g. product reviews, wishlists),
follow the same 6-step recipe as above (Domain, Application,
Infrastructure/Lunar, composition root, Http + routes, frontend + types) and
use `app/Domain/Checkout/**` as the most recent reference.

### Customer accounts and order history

An order placed while logged in is linked to the account with **zero custom
code**: `Lunar\Base\Traits\LunarUser` (already on `App\Models\User`) plus
Lunar's own `CartSessionAuthListener`/`CartSessionManager` assign the cart's
`user_id` automatically on login and at cart creation — this template only
had to add the read side.

- `GET /account/orders` and `GET /account/orders/{reference}` (`App\Domain\Account\Port\OrderHistory`,
  `app/Infrastructure/Lunar/Account/LunarOrderHistory.php`) list an account's
  placed orders and show one in detail, reusing the same `OrderMapper` as the
  checkout confirmation page.
- Both routes sit behind the `auth` middleware, and — this is the important
  part — the adapter scopes every query to `Auth::user()` itself
  (`$user->orders()->where(...)`) rather than trusting a reference from the
  URL. There is no code path where one account can load another's order; a
  mismatched reference is a plain 404, not an authorization error, so it
  leaks no information about whether the order exists. Covered by
  `tests/Feature/Storefront/AccountOrdersTest.php`, including a
  cross-account isolation test.
- Guest checkout still works exactly as before — orders placed while logged
  out simply have no `user_id` and won't appear in anyone's history. Instead,
  `GET /orders/lookup` ("Track an order" in the footer) lets a guest find
  their order with the reference + the email given at checkout, the same
  pattern most stores use for exactly this case
  (`App\Application\Account\LookupGuestOrder`,
  `OrderHistory::findByReferenceForGuest()`). A wrong reference and a
  correct reference with the wrong email produce the **exact same** generic
  error — the two cases are indistinguishable on purpose, so no guess ever
  confirms that a given order reference exists. Like the checkout
  confirmation page, the result page reads only from the session, never a
  reference from the URL. Throttled at 10/min/IP (`order-lookup` limiter),
  the same strictness as payment completion, since it's a two-field
  guessing surface. Covered by `tests/Feature/Storefront/GuestOrderLookupTest.php`.

### Stock management

Lunar validates stock on every cart mutation by default
(`config/lunar/cart.php`'s `CartLineStock` validator) — but only for a
variant explicitly marked `purchasable = 'in_stock'` with a `stock` count;
the model's own default, `purchasable = 'always'`, is unlimited/made-to-order
and skips the check entirely. `database/seeders/DemoCatalogSeeder.php` sets
both fields so the demo catalog actually enforces stock out of the box, with
one product seeded at each state (plenty, low, zero) to exercise all three UI
states.

- The product page shows "Only N left" under a low-stock threshold and
  disables "Add to cart" entirely at zero — `available_stock` is exposed on
  every variant (`App\Domain\Catalog\ProductVariant`), `null` meaning
  unlimited.
- Trying to add or update past the available stock is rejected server-side
  with a friendly message rather than a 500: Lunar's `CartException` is
  caught at the one place that's allowed to know about it
  (`App\Infrastructure\Lunar\Cart\LunarCartGateway`) and re-thrown as
  `App\Domain\Cart\CartLineException`, which `CartController` turns into a
  normal Inertia form error. This also covers Lunar's other cart-line
  validators for free (quantity below minimum, quantity increment, product
  no longer purchasable) — same exception, same handling.
- A real store manages stock counts at `/lunar/products/{id}` like any other
  product field; nothing about how it's read changes.

### Product search

`GET /search?q=...` (a search bar in the storefront header on every page) is
built on Lunar's own `Searchable` trait (`Lunar\Models\Product` already has
it) rather than a hand-rolled `LIKE` query — it's Laravel Scout underneath,
and Lunar ships the indexer that flattens each product's translated
`attribute_data` (name, description, ...) into a plain searchable document
for it.

- Zero extra infrastructure by default: `SCOUT_DRIVER=collection` (Scout's
  own default, set explicitly in `.env`/`.env.example` for clarity) does an
  in-memory, case-insensitive substring match — no service to run, no
  `artisan scout:import` step, works offline and in CI. It's the right
  choice for a small catalog and the wrong one for a large one: it loads
  every row into memory per search.
- Upgrading to a real, relevance-ranked index (Meilisearch, Algolia,
  Typesense — all supported by Scout) is a config change, not a rewrite:
  switch `SCOUT_DRIVER`, run `artisan scout:import "Lunar\Models\Product"`,
  done. `App\Infrastructure\Lunar\Catalog\LunarProductCatalog::search()`
  doesn't change either way.
- Search only ever returns `status = 'published'` products in the current
  channel — the same rule as every other catalog listing. Scout resolves
  matching ids first, then a normal Eloquent query re-applies that rule and
  the usual eager loading before anything reaches `ProductMapper`.
- Which attributes are searchable is admin-configurable at
  `/lunar/attribute-groups` (the `searchable` flag Lunar already exposes per
  attribute) — nothing on this template's side hardcodes "name" and
  "description".

### Coupon codes

Discounts (percentage/fixed-amount off, buy-X-get-Y, product/collection/brand
restrictions, min spend, per-customer/global usage limits...) are a Lunar
core feature managed entirely at `/lunar/discounts` — this template adds only
the missing storefront half: a coupon code field.

- A cart's `coupon_code` is a real column Lunar's own `ApplyDiscounts` cart
  pipeline already reads on every recalculation — applying a coupon is
  really just setting that column and recalculating
  (`App\Infrastructure\Lunar\Cart\LunarCartGateway::applyCoupon()`), not a
  parallel discount-matching system.
- A code that doesn't exist/is expired **and** a code that's valid but
  doesn't apply to anything currently in the cart (wrong products, cart
  below the discount's minimum spend...) are both rejected with a specific,
  distinct message, and never left silently "applied" for $0 off — checked
  by recalculating and looking at the resulting `discount_total`, since
  Lunar's own `Discounts::validateCoupon()` only checks the code itself, not
  whether it does anything for this specific cart.
- Unlike the guest-order-lookup error (deliberately generic to prevent order
  enumeration), these two coupon messages are intentionally distinct: a
  coupon code isn't tied to anyone's personal data, so there's nothing to
  protect by hiding which case happened, and telling them apart is more
  useful to a real shopper.
- The cart page shows a plain code input, switching to "Code X applied
  [Remove]" once one's set — no discount breakdown by line, since Lunar
  already shows that in its own order/cart admin views.

## Getting started

### Prerequisites

Lunar requires PostgreSQL or MySQL — **no SQLite**. A `docker-compose.yml`
provides a PostgreSQL 16 `db` service for local dev.

```sh
docker compose up -d db
```

The host doesn't need PHP or Node installed: this project ships a dev PHP
Docker image (`docker/php/Dockerfile.dev`) and wrapper scripts (`bin/composer`,
`bin/artisan`, `bin/php`, `bin/npm`, `bin/npx`) that run composer/artisan/npm
in disposable containers, without installing anything on the machine.

### Installation

```sh
./bin/composer install
cp .env.example .env   # already points to pgsql:5436/app
./bin/artisan key:generate
./bin/artisan lunar:create-admin --firstname=Admin --lastname=User --email=admin@example.com --password=password
./bin/artisan lunar:install --no-interaction
./bin/artisan db:seed          # creates a test user + the demo catalog
./bin/artisan storage:link      # serves uploaded/seeded product images
./bin/npm install
./bin/npm run build             # or `./bin/npm run dev` for hot reload
./bin/artisan queue:work         # processes seeded images' conversions (see below) - Ctrl+C once done, or use `./bin/composer dev` instead of the artisan/npm commands above
```

`lunar:create-admin` must run before `lunar:install --no-interaction` (the
installer checks that an admin already exists to skip the interactive step).
`lunar:install` also imports the full country reference data
(`lunar:import:address-data`), needed by the Checkout address form.
`./bin/artisan db:seed` also seeds the demo shipping zone/methods and a
demo tax rate (see "The Checkout flow" above) - without them, Checkout has
no shipping options to offer and charges no tax.

First login at `/lunar` walks the new admin through setting up an
authenticator app: two-factor authentication is required, not optional (see
above) - have one ready (Google Authenticator, 1Password, etc.).

### Configuring Stripe (needed to test payment)

1. Create a Stripe account (test mode is enough) and grab the keys from
   [dashboard.stripe.com/test/apikeys](https://dashboard.stripe.com/test/apikeys).
2. Fill in `.env`: `STRIPE_KEY` (secret), `STRIPE_PUBLIC_KEY` (publishable).
3. Locally, expose webhooks with the Stripe CLI:
   `stripe listen --forward-to localhost:8000/stripe/webhook` — it prints a
   `whsec_...` to put in `STRIPE_WEBHOOK_SECRET`. In prod, this secret comes
   from the endpoint's configuration in the Stripe dashboard.
4. Test a payment with the test card `4242 4242 4242 4242`, any future date,
   and any CVC.

Without valid Stripe keys, the whole flow works up to the payment step
(address, shipping, summary): only PaymentIntent creation fails
(`Invalid API Key provided`).

### Transactional email

Placing an order queues `App\Mail\OrderConfirmationMail` (see
`CompleteCheckout`), sent to the email address collected in the checkout's
"Email" field (billing address only — required, since it's the only way to
reach a guest checkout). Locally:

```sh
docker compose up -d mailpit   # inbox at http://localhost:8025
./bin/artisan queue:work        # processes the queued mail (QUEUE_CONNECTION=database)
```

`.env.example` already points `MAIL_MAILER`/`MAIL_HOST`/`MAIL_PORT` at
Mailpit — nothing else to configure locally. In production, point the same
`MAIL_*` variables at a real provider (Postmark, SES, Resend, plain SMTP...);
Laravel's mail config is already provider-agnostic, no code change needed
either way. Auth emails (password reset, email verification — already part
of the starter kit) go through the same config for free.

A queue worker has to actually be running for this (or the queue backs up
silently, no error) — `./bin/composer dev` (the starter kit's own script)
already runs one alongside `serve`/Vite; the production topology's `worker`
service (see "Production deployment" below) covers this for real
deployments. The same requirement applies to product image conversions,
next.

### Product images

Product photos go through Lunar's own media library
(`spatie/laravel-medialibrary`, already wired on `Lunar\Models\Product` —
nothing built for this template, it's core Lunar): upload one at
`/lunar/products/{id}/media`, or programmatically with
`$product->addMedia($path)->toMediaCollection('images')`
(`database/seeders/DemoCatalogSeeder.php` does exactly this, generating a
plain placeholder image per demo product with GD so the template needs no
bundled binary assets and works offline/in CI). The first image uploaded to
a product is automatically marked as its thumbnail
(`Lunar\Observers\MediaObserver`) — no extra step.

**Conversions (thumbnails, `small`/`medium`/`large`/`zoom`) are generated by
a queued job**, same as transactional email above: run
`./bin/artisan queue:work` (or `./bin/composer dev`) after seeding or
uploading an image, or the storefront/admin will show a broken thumbnail
until the job runs. `./bin/artisan storage:link` must have been run too (the
production Dockerfile already does this at build time; for local dev it's a
one-time manual step) for `/storage/...` URLs to resolve to real files.

### Legal pages

`/terms` and `/privacy` (linked from the footer) are **structural
placeholders**, not usable legal text: every `[bracketed]` value needs
filling in with real business specifics, and the final wording needs a
lawyer's review for your jurisdiction before going live. What they do
accurately describe already: Stripe as the payment processor, no card data
stored locally, and that only strictly-necessary cookies (session, CSRF) are
set by default — update that last point if you add analytics or marketing
tools later.

### Running it

```sh
./bin/artisan serve
```

- `/` — home page, featured products
- `/collections/{slug}`, `/products/{slug}` — catalog navigation
- `/cart` — cart (add/update/remove lines, live Lunar totals)
- `/checkout` — address, shipping, Stripe payment
- `/checkout/confirmation` — placed order summary
- `/lunar` — Lunar's Filament back office (orders visible under `/lunar/orders`)

### Tests

```sh
docker compose up -d db
./bin/artisan test
```

Tests run against a real PostgreSQL database (`app_testing`, see
`phpunit.xml`), not a mock — Lunar doesn't support SQLite and its
price/tax calculation pipeline only makes sense against real SQL queries.

`Tests\TestCase` automatically seeds Lunar's base data (channel, currency,
tax zone, a country...) via `Database\Seeders\LunarDefaultsSeeder` for every
database refreshed by `RefreshDatabase` — without this, even a simple login
(which triggers `Lunar\Listeners\CartSessionAuthListener`) fails on a
freshly migrated database.

`tests/Feature/Storefront/CheckoutTest.php` simulates Stripe with
`Lunar\Stripe\Facades\Stripe::fake()` (provided by the package): no test
ever calls the real Stripe API.

`tests/Unit/Domain/Shared/MoneyTest.php` extends `PHPUnit\Framework\TestCase`
directly (not `Tests\TestCase`) to prove the domain boots no framework at
all.

### Code quality (Pint, PHPStan, ESLint, Prettier, tsc)

```sh
./bin/composer lint       # Pint - fixes PHP style
./bin/composer test:lint  # Pint --test - checks without fixing (CI)
./bin/composer analyse    # PHPStan/Larastan (level 5)
./bin/npm run format      # Prettier - fixes frontend style
./bin/npm run lint        # ESLint --fix
./bin/npm run typecheck   # tsc --noEmit
```

`phpstan.neon` includes `phpstan-baseline.neon`, which ignores ~30 false
positives all confined to `app/Infrastructure/Lunar/**`: Lunar resolves its
models through methods like `Currency::modelClass()` (to stay swappable via
config), which Larastan can't follow statically — the real type at runtime
is correct (verified by the test suite), see the comment at the top of that
file for details. `app/Domain` and `app/Application` need zero entries in
this baseline: that's the signal to watch for if a typing regression ever
showed up there.

### Dependency auditing

```sh
./bin/composer audit  # PHP: known advisories in installed packages
./bin/npm audit        # Frontend: same, capped at high/critical (see below)
```

`npm audit` is run with `--audit-level=high` (locally via `npm run audit`,
and in CI): moderate/low findings in transitive dev-tooling packages are
common noise that isn't worth blocking a merge over, but high/critical
findings fail the build. `composer audit` has no such filter — any known
advisory fails, since the PHP dependency tree is smaller and each advisory
here is worth looking at directly.

`.github/dependabot.yml` opens a weekly PR for outdated dependencies
(composer, npm, both Dockerfiles, GitHub Actions) so advisories get caught
before `npm audit`/`composer audit` even has to.

### Rate limiting

Anonymous browsing (catalog, product pages, viewing the cart/checkout) isn't
throttled — it carries no more abuse risk than any public page. State-changing
storefront actions are, via two named limiters registered in
`AppServiceProvider::configureRateLimiting()` and applied in
`routes/storefront.php`:

- `storefront-write` (30/min per IP) — adding/updating/removing cart lines,
  submitting the checkout address, selecting a shipping option.
- `checkout-payment` (10/min per IP) — `POST /checkout/complete` and
  `GET /checkout/return`. Payment completion gets its own, stricter limiter
  because it's the classic **card-testing target**: fraudsters run large
  numbers of stolen card numbers through a checkout endpoint to find which
  ones still work. Per-IP throttling here is a first line of defense, not a
  complete solution on its own — a motivated attacker rotates IPs, which is
  exactly what Stripe Radar (not this template) is built to catch.

Both limiters are keyed by IP rather than a logged-in user: the storefront
has no authenticated "customer" concept of its own (Lunar carts are
guest-friendly by design), so IP is the only identity available for an
anonymous shopper. `tests/Feature/Storefront/RateLimitingTest.php` proves
both limiters actually return `429` past their threshold, not just that
they're declared somewhere.

### Error tracking (Sentry SDK -> GlitchTip)

Uncaught exceptions are reported via `sentry/sentry-laravel`, wired in
`bootstrap/app.php` (`Integration::handles($exceptions)`). This template
points it at [GlitchTip](https://glitchtip.com) rather than Sentry's own
SaaS — same wire protocol (GlitchTip implements Sentry's ingestion API), so
the official Sentry SDK works unmodified, but self-hosted and without
Sentry-specific vendor lock-in. Swapping to real Sentry, or dropping error
tracking entirely, is a one-line `SENTRY_LARAVEL_DSN` change either way.

Left unset (the default), the SDK simply doesn't send anything — nothing
else in the app depends on it.

To try it locally:

```sh
docker compose -f docker-compose.glitchtip.yml up -d
# wait ~30s, then open http://localhost:8010, create an account (the first
# user becomes an admin) and a project to get a DSN
```

Paste that DSN into `SENTRY_LARAVEL_DSN` in `.env`, then confirm it works
end-to-end with `php artisan sentry:test` (or trigger any real exception —
both paths were verified against a live local GlitchTip instance while
building this: an SDK test event, a real thrown-and-reported exception, and
an actual unhandled error all landed as distinct issues).
`docker-compose.glitchtip.yml` is sized for local development only — self-host
GlitchTip on its own infrastructure for real production use (see
[glitchtip.com/documentation/install](https://glitchtip.com/documentation/install)).

### CI (GitHub Actions)

- `.github/workflows/tests.yml` — full PHPUnit suite against a real Postgres
  service (no SQLite, see above).
- `.github/workflows/lint.yml` — Pint, PHPStan, Prettier, ESLint, tsc,
  `composer audit` and `npm audit`, all in strict check mode (`--test`/
  `--check`, never `--fix` in CI: a CI job should never silently modify
  code, only fail if something doesn't meet the rules).

## Production deployment (Docker + FrankenPHP)

The `Dockerfile` at the root (distinct from `docker/php/Dockerfile.dev`,
local tooling only) builds a production image with
[FrankenPHP](https://frankenphp.dev) in **classic mode** (not worker/Octane
mode): every request restarts the application cleanly, like
`php artisan serve`/php-fpm+nginx today. This is a deliberately cautious
choice for a template meant to be reused — worker mode (the app stays
loaded in memory between requests) is much faster but requires auditing all
the code for state leaks between requests (singletons, Lunar cart, Stripe
session...), an easy trap to introduce without thinking about it as a
project grows. See [FrankenPHP's worker mode docs](https://frankenphp.dev/docs/worker/)
if performance becomes a real concern later.

### Build and run

`docker-compose.prod.yml` demonstrates the full topology — a single image,
run as several services with different commands (not an "all-in-one"
container):

```sh
cp .env.example .env.prod   # then fill in APP_KEY (php artisan key:generate --show),
                             # DB_*, STRIPE_*, MAIL_*, etc. — never commit this file

docker compose -f docker-compose.prod.yml build

# Deployment step, NOT automatic on container startup (see
# frankenphp/docker-entrypoint.sh): running a migration from the web
# service's entrypoint is risky as soon as there are multiple replicas.
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force

# First deployment only (idempotent otherwise, `lunar:install` stops itself
# if the base data already exists):
docker compose -f docker-compose.prod.yml run --rm app php artisan lunar:create-admin --firstname=... --lastname=... --email=... --password=...
docker compose -f docker-compose.prod.yml run --rm app php artisan lunar:install --no-interaction

docker compose -f docker-compose.prod.yml up -d
```

**Never run `php artisan db:seed` in production**: `DatabaseSeeder` creates
a *demo* user and catalog via factories (`fakerphp/faker`), a **dev-only**
package absent from the production image (`composer install --no-dev`) —
the command actually fails immediately if you try
(`Call to undefined function fake()`), which is the intended behavior:
nobody wants fake products in a real store.

**`ShippingDefaultsSeeder` and `TaxDefaultsSeeder` are the exception** —
neither uses factories, so
`php artisan db:seed --class=ShippingDefaultsSeeder --force` and
`... --class=TaxDefaultsSeeder --force` are safe (and needed) in production
to get a first shipping zone/method and tax rate in place; edit them first
to match the real store's actual rates, or skip them entirely and configure
everything by hand at `/lunar/shipping-zones` and `/lunar/tax-rates`.

### Topology

`docker-compose.prod.yml` starts three services from the same image: `app`
(web server, port exposed), `worker` (`php artisan queue:work`), and
`scheduler` (`php artisan schedule:work`), plus `db` (to be replaced by a
managed Postgres in real production). It's the same Docker image for all
three — only the command changes.

### SERVER_NAME (domain vs. behind a reverse proxy)

`frankenphp/Caddyfile` reads `SERVER_NAME`: a real domain name
(`SERVER_NAME=shop.example.com`) makes Caddy automatically obtain an HTTPS
certificate (Let's Encrypt); if the container is already behind another
reverse proxy/load balancer that terminates TLS (the most common case on
most PaaS/k8s setups), leave `SERVER_NAME` unset (defaults to `:80`, plain
HTTP).

### Verified

The image was actually built and run (not just reviewed): full multi-stage
build, migrations against a fresh Postgres, `lunar:install`, demo catalog
seeded, and a real HTTP flow (home page with products, adding to cart with
correct Lunar totals, Filament admin panel reachable) against the FrankenPHP
container running as `www-data` (non-root).

## Known limitations

- Live carrier rate lookups (USPS/UPS/DHL APIs, real-time quotes) aren't
  wired up — table-rate shipping (weight/zone/price tiers configured by the
  store) is, see "The Checkout flow" above.
- No saved address book — customers re-type their address on every order.
  Order history for logged-in accounts (`/account/orders`) and guest lookup
  by reference + email (`/orders/lookup`) are both built — see "Customer
  accounts and order history" above.
- Refunds and other post-order actions: already covered by Lunar's Filament
  panel, not reimplemented on the storefront side.
- No shipping/delivery-status emails beyond the order confirmation — no
  "your order has shipped" notification, since nothing in this template
  tracks fulfillment status yet.
- `/terms` and `/privacy` are structural placeholders, not final legal text —
  see "Legal pages" above.
- A few auth pages from the starter kit (`login.tsx`, `register.tsx`,
  `reset-password.tsx`) have a pre-existing TypeScript error on the
  `useForm<T>` generic, unrelated to this template (not fixed, out of
  scope).
