# Features in depth

What each storefront context does and the Lunar behaviour behind it. Back to
the [README](../README.md).

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

## Known limitations

- Live carrier rate lookups (USPS/UPS/DHL APIs, real-time quotes) aren't
  wired up — table-rate shipping (weight/zone/price tiers configured by the
  store) is - see [The Checkout flow](#the-checkout-flow-address---shipping---stripe-payment---order).
- No saved address book — customers re-type their address on every order.
  Order history for logged-in accounts (`/account/orders`) and guest lookup
  by reference + email (`/orders/lookup`) are both built — see [Customer
  accounts and order history](#customer-accounts-and-order-history).
- Refunds and other post-order actions: already covered by Lunar's Filament
  panel, not reimplemented on the storefront side.
- No shipping/delivery-status emails beyond the order confirmation — no
  "your order has shipped" notification, since nothing in this template
  tracks fulfillment status yet.
- `/terms` and `/privacy` are structural placeholders, not final legal text —
  see [Legal pages](development.md#legal-pages).
- A few auth pages from the starter kit (`login.tsx`, `register.tsx`,
  `reset-password.tsx`) have a pre-existing TypeScript error on the
  `useForm<T>` generic, unrelated to this template (not fixed, out of
  scope).
