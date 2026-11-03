# Development

Install, configure, run and verify the project locally. Back to the
[README](../README.md).

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

Build that image once before using the `bin/` wrappers (the npm ones use the
official `node` image directly and need no build):

```sh
docker build -f docker/php/Dockerfile.dev -t lunar-starter .
```

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
./bin/artisan queue:work         # processes seeded images' conversions (see below) - Ctrl+C once done
```

`lunar:create-admin` must run before `lunar:install --no-interaction` (the
installer checks that an admin already exists to skip the interactive step).
`lunar:install` also imports the full country reference data
(`lunar:import:address-data`), needed by the Checkout address form.
`./bin/artisan db:seed` also seeds the demo shipping zone/methods and a
demo tax rate (see [The Checkout flow](features.md#the-checkout-flow-address---shipping---stripe-payment---order)) - without them, Checkout has
no shipping options to offer and charges no tax.

First login at `/lunar` walks the new admin through setting up an
authenticator app: two-factor authentication is required, not optional
(see [Architecture](architecture.md#why-hexagonal-and-how-far)) - have one
ready (Google Authenticator, 1Password, etc.).

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
silently, no error) — run `./bin/artisan queue:work` in its own terminal.
(The starter kit's `composer dev` script bundles worker + serve + Vite, but
only with a native PHP *and* Node install: the `bin/` PHP image has no
Node.) The production topology's `worker`
service (see [Deployment](deployment.md)) covers this for real
deployments. The same requirement applies to product image conversions,
next.

### Product images

Product photos go through Lunar's own media library
(`spatie/laravel-medialibrary`, already wired on `Lunar\Models\Product` —
nothing built for this template, it's core Lunar): upload one at
`/lunar/products/{id}/media`, or programmatically with
`$product->addMedia($path)->toMediaCollection('images')`
(`database/seeders/DemoCatalogSeeder.php` does exactly this). The first image
uploaded to a product is automatically marked as its thumbnail
(`Lunar\Observers\MediaObserver`) — no extra step.

The demo seeder pulls two real photos of each product (the same item from two
angles, so the product page's gallery has something to show) from a public
demo-product CDN, at seed time rather than from files committed here — they
stay out of the repository and land only in your own storage. No network, or
an unreachable CDN? Each image falls back to a generated GD placeholder, so
seeding never fails on someone else's uptime. Note the photos are `.webp`:
the GD build in both images enables WebP for exactly this reason, otherwise
the conversions below would fail on them.

**Conversions (thumbnails, `small`/`medium`/`large`/`zoom`) are generated by
a queued job**, same as transactional email above: run
`./bin/artisan queue:work` after seeding or
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

`tests/Feature/Storefront/QueryBudgetTest.php` asserts something no other
test can see: that the work a page does stays the same size as the catalogue
grows. It counts queries and bind parameters rather than timing anything, so
it says the same thing on a laptop and on a shared CI runner, and it is what
caught two of the N+1s listed in `benchmarks/README.md`. It is also the one
class that opts out of the suite's drivers: `phpunit.xml` runs on `array`
sessions and cache, which is right everywhere else and hides the two queries
every real page view spends on the `sessions` table, so this one switches
itself to `database` - and turns off session garbage collection, which is a
2-in-100 lottery and would otherwise make the budget a dice roll.

### Benchmarks (k6)

```sh
BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/smoke.js
```

Load tests for the storefront, in [Grafana k6](https://k6.io).
`./bin/k6` runs a pinned k6 in Docker, like the other `bin/` wrappers —
nothing to install. Seven scenarios: a smoke pass, a capacity ramp, the read
path at a held arrival rate, search against a listing of the same catalogue,
cart mutations, a whole purchase end to end, and the write limiter under
flood. **[benchmarks/README.md](../benchmarks/README.md)** has what each one
measures, the knobs, and a measured baseline.

Two things are worth knowing before running any of it.

**Don't benchmark `php artisan serve`.** It is a development server with no
cached config and no useful opcache; its numbers describe PHP's built-in
server, not this application. `docker-compose.bench.yml` stands up the
production image (FrankenPHP, opcache, the caches its entrypoint builds)
on port 8099 with a database of its own — the bootstrap sequence is in that
file's header.

**The write limiter will stop you.** `throttle:storefront-write` allows 30
requests a minute per IP and a load generator is one IP, so `cart.js` and
`journey.js` count their rate per minute and fail on a single 429. See
"Rate limiting" below — including why a refused request still costs about half
an accepted one, and why moving the limiter ahead of the session is not the
fix it looks like.

What came out of it on a four-core machine, in short: the production image
holds about fifteen requests a second in classic FrankenPHP mode, flat to
twelve, with no request failing even when saturated; a cart mutation costs
~280 ms at p95 and a whole purchase ~2.2 s of server time; the write limiter
accepts exactly its thirty and spends about half the cost of an accepted
write on each one it refuses.

**Three things it found have since been fixed**, and
[benchmarks/README.md](../benchmarks/README.md) carries both columns:

- Search, with the default `SCOUT_DRIVER=collection`, took **78 seconds** and
  142 MB on a 10 000-product catalogue — Scout's indexless engine loads the
  catalogue into PHP, and a term matching nothing cost the same as one
  matching everything. It is now a database query: 917 ms.
- A twelve-product collection page ran **106 queries**. The mapper reads
  `defaultUrl`, `thumbnail` and each variant's price, and none was
  eager-loaded. Now **10**, and the page went 258 ms → 67 ms.
- `?sort=price_asc` on a 2 000-product collection took **5.35 s** as a
  correlated subquery, re-running `min(price)` per candidate product. Joined
  once as an aggregate: 280 ms.
- `/sitemap.xml` took **1.08 s**, and not because of the XML — generating its
  10 022 URLs costs 133 ms. It fetched every published product id into PHP and
  sent all ten thousand back as bind parameters. Left as a subquery: one
  query, 348 ms.

`artisan bench:catalog --products=2000 --force` is what grows the catalogue to
measure that; it generates products with deterministic names and spread
prices, and no images (a media conversion per product costs minutes and
changes nothing about the queries).

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

Its updates are **grouped**, because several of these packages cannot be
bumped on their own: `laravel-vite-plugin` and `@vitejs/plugin-react` both
require a matching Vite major, Inertia's PHP and JS halves have to move
together, and PHPUnit drags its own components along. Two majors are also
deliberately held back, each with the reason in the file: ESLint 10, which
`eslint-plugin-react` still doesn't accept, and Node, whose odd-numbered
releases are never LTS.

After pulling a dependency upgrade, run:

```sh
./bin/artisan view:clear
```

Blade compiles `@inertia` into the cached view, and Inertia 3 changed what
that directive emits. Without clearing, the app boots against the old markup
and renders **a blank page with no server-side error** — the failure looks
like a broken build rather than a stale cache. Production is safe as long as
the deploy runs `view:cache` after installing.

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

**A refusal is cheap, not free.** `benchmarks/rate-limit.js` flooded the write
route from one IP: exactly 30 requests got through and 604 were refused, and a
refused one cost 441 ms against 887 ms for an accepted one. `throttle:` is
route middleware, so it runs after the `web` group — by the time a request is
refused, Laravel has booted, decrypted the session cookie, read the session row
from Postgres and verified the CSRF token. The limiter protects the cart, not
the framework.

Moving it ahead of the session does not work, and the reasons are worth knowing
before anyone tries. `Middleware::prependToPriorityList()` is a no-op here —
`ThrottleRequests` is already in Laravel's priority list and the merge skips
anything already present — so it would mean replacing the whole list and
pinning framework internals into `bootstrap/app.php`. And that order is
deliberate: `ThrottleRequests::resolveRequestSignature()` keys on
`$request->user()` when there is one, which is exactly why Laravel runs it
after the session and auth. `routes/auth.php` has two `throttle:6,1` routes
inside `Route::middleware('auth')`; the reorder would silently re-key them from
per-user to per-IP and put a whole office behind one shared budget. At the
scale where the cost of a refusal matters, the answer belongs in front of the
application, not inside it.

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

