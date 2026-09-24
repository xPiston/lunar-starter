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

### Listings: paging, sorting, filtering

Collection pages and search results are paged (24 per page), sortable
(newest, price up or down, alphabetical) and filterable (price range, in
stock only). Before this, both were capped at a hard limit of 24 rows —
**every product past the second dozen was unreachable**, from a visitor and
from a crawler alike.

- The port speaks in a `ProductQuery` (page, sort, filters) and answers with
  a `ProductListing` (the rows plus the total). One object rather than a
  growing parameter list, so the next filter doesn't change the signature of
  every method that carries it, and a caller can never again receive rows
  without knowing whether there are more.
- `sort` is validated against the `ProductSort` enum before it reaches an
  `order by`, so no column name can arrive from a query string. `per_page` is
  capped for the same class of reason: nobody gets to ask for the whole
  catalogue in one request.
- Sorting by price orders on the **base** price (current currency, single
  unit, no customer group), because the price `Pricing::for()` resolves
  depends on who is looking and cannot be a column to sort a page by. With no
  group pricing — the default here — the two agree exactly; with it, a
  listing can disagree with a card's "from" price, and the answer at that
  point is a search index holding a price per group, not a heavier join.
- Ordering by name reads the `name` attribute out of the JSON column, which
  assumes it is plain `Text`, as Lunar ships it. Switched to `TranslatedText`
  it sorts by the raw JSON instead: wrong, harmless, and fixed with a
  generated column or that same index.
- Every sort ends on the id so rows comparing equal keep a fixed order
  between two requests. Note this is defensive, not test-proven: Postgres
  returns tied rows consistently at the sizes tested, so removing the
  tie-breaker leaves the suite green. The database guarantees nothing about
  ties, which is reason enough to keep it.
- A plain page 2 is indexable — it holds products nothing else links to. A
  filtered or reordered listing is `noindex`: every combination is the same
  catalogue sliced differently, and crawlers would spend their budget
  enumerating them.

### Product reviews

Signed-in customers rate a product out of five and say why; nothing appears
until a member of staff approves it at `/lunar/product-reviews`, where the
navigation item carries the number still waiting.

- **Moderation is enforced by the adapter**, through a scope every storefront
  read goes through. A pending review is invisible on the page *and* absent
  from the average — a controller cannot publish one by forgetting a
  condition, which is what most of
  `tests/Feature/Storefront/ProductReviewTest.php` checks.
- Reviews are behind `auth`. A review carries a name and a "verified
  purchase" badge, and neither means anything without an identity; an
  anonymous form is a spam target needing moderation tooling this template
  doesn't ship. One review per customer per product, enforced by a unique
  index as well as by the use case.
- The **verified purchase** badge is decided once, when the review is written,
  by asking a second port: `PurchaseCheck`, implemented against Lunar's
  orders. Reviews are the application's own table, purchases are Lunar's, and
  the use case composes the two — a decision, not a join. Recomputing it later
  would let a refunded order silently revoke a badge already shown.
- Authors appear as "Marie D." — first name, last initial. Enough to read as a
  person without publishing a customer's full name next to their opinions.
  The domain object carries no email or user id at all.
- `AggregateRating` joins the product's JSON-LD **only once there is a review**:
  an aggregate with a count of zero is invalid structured data, and one bad
  key invalidates the whole block — which would take the price and
  availability snippet down with it. Null entries are now stripped from that
  block for the same reason.
- Ratings are on the product page, not yet on product cards in listings: that
  needs an aggregate per row in the catalogue queries, and it is the obvious
  next step rather than something silently missing.

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
- `database/seeders/DiscountDefaultsSeeder.php` seeds one working coupon,
  `WELCOME10` (10% off, no restrictions), so the discount box isn't a dead end
  in a fresh install - without a single Discount row every code is rejected and
  the feature looks broken rather than unused.
- The cart page shows a plain code input, switching to "Code X applied
  [Remove]" once one's set — no discount breakdown by line, since Lunar
  already shows that in its own order/cart admin views.

### Abandoned cart reminders

One email to whoever left items behind, with a link that puts the cart back in
their browser. `carts:send-abandoned-reminders` is scheduled hourly in
`routes/console.php`, so **nothing is sent unless the scheduler is running** —
in this template's production topology that's the dedicated `scheduler`
service (see [deployment](deployment.md)).

- "Abandoned" means idle on both sides: the cart row *and* every one of its
  lines. Lunar's cart lines don't touch their cart's `updated_at`, so a check
  on the cart alone would treat a cart someone is actively filling as
  untouched since the moment it was created, and email them mid-session.
- The rules live behind the `CartReminders` port, not at the call site:
  completed, emptied, merged, unreachable, already-reminded and unsubscribed
  carts never come out of `listDue()`, so no caller can forget one of them.
  `tests/Feature/Storefront/AbandonedCartReminderTest.php` is mostly about
  that restraint.
- A cart is marked as reminded **before** the mail is queued. If the mail
  driver throws, the reminder is lost rather than repeated: a shop that emails
  someone twice about the same cart looks broken in a way the customer can see.
- Reachability comes from the account's address first, then the address typed
  into checkout. A guest who never reached checkout left no address anywhere
  and is simply never contacted.
- Both links in the email are **signed, expiring URLs** (`signed` middleware).
  Without a signature, editing the id in one link would restore any cart in
  the shop. A cart attached to an account is only handed over to that account
  signed in — a forwarded email otherwise gives away the address on it; the
  visitor is sent to log in and lands back on the link afterwards.
- Every email carries a one-click unsubscribe, recorded per address. A
  reminder is marketing, not a receipt, and shipping one without an opt-out
  puts the shop's owner on the wrong side of GDPR/CAN-SPAM.
- `ABANDONED_CART_REMINDERS_ENABLED=false` switches the whole thing off; the
  delay, the maximum cart age, the link lifetime and the batch size are in
  `config/abandoned_carts.php`.

## SEO

Metadata is rendered by `resources/views/app.blade.php`, from a `meta` prop
each controller supplies (`App\Http\Seo\PageMeta`). **Server-side on
purpose**: Inertia's own `<Head>` patches the DOM after the JS boots, which
Google tolerates but no social crawler does - Facebook, Slack, WhatsApp and
LinkedIn read the HTML as served and never run scripts. Anything that has to
survive being shared has to come out of Blade.

- Every response carries a title, description, canonical URL, Open Graph and
  Twitter Card tags. `HandleInertiaRequests` shares a default built from
  `config/seo.php`, so a page that says nothing still ships complete metadata.
- Product pages add the parts that matter for a shop: the full-size photo as
  the share image (not the thumbnail - previews render around 1200x630), JSON-LD
  `Product` structured data with price and stock availability, and the
  `product:price:*` Open Graph properties Facebook and Pinterest read.
- Carts, checkout, order pages and search results are `noindex, nofollow` -
  thin, duplicated or private, and nothing you want crawlers spending budget
  on. Covered by `tests/Feature/Storefront/SeoTest.php`, which asserts against
  the rendered HTML rather than the props.
- `/sitemap.xml` and `/robots.txt` are generated (`SitemapController`), not
  static files: the sitemap follows the catalogue, and robots.txt has to name
  the sitemap with an absolute URL that depends on `APP_URL`. A static file in
  `public/` would also be served before ever reaching PHP.

Set `APP_NAME`, `SEO_DESCRIPTION` and `SEO_IMAGE` (an absolute URL to a
1200x630 PNG or JPEG - crawlers won't render SVG) for your own store.

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
- No shipping/delivery-status emails — the order confirmation and the
  abandoned cart reminder are the only two the template sends. "Your order
  has shipped" would need fulfillment status, which nothing here tracks yet.
- One reminder per abandoned cart, not a sequence. A second and third email
  on a delay is the usual next step, and the schedule + `cart_reminders`
  table are where it would go.
- `/terms` and `/privacy` are structural placeholders, not final legal text —
  see [Legal pages](development.md#legal-pages).
- A few auth pages from the starter kit (`login.tsx`, `register.tsx`,
  `reset-password.tsx`) have a pre-existing TypeScript error on the
  `useForm<T>` generic, unrelated to this template (not fixed, out of
  scope).
