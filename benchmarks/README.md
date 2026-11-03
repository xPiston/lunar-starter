# Benchmarks (k6)

Load tests for the storefront, written for [Grafana k6](https://k6.io).
Back to the [README](../README.md) and [docs/development.md](../docs/development.md).

Nothing to install: `./bin/k6` runs a pinned k6 in Docker, exactly like
`./bin/php` and `./bin/composer`.

```sh
BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/smoke.js
```

## The scenarios

| Script          | Question it answers                                              | Default load      |
| --------------- | ---------------------------------------------------------------- | ----------------- |
| `smoke.js`      | Does every path the others use still work?                       | 1 VU, 1 pass      |
| `capacity.js`   | How many requests a second can this target take?                 | ramp to 20/s      |
| `browse.js`     | What does a shopper wait for while others browse?                | 8 req/s           |
| `search.js`     | What does a search cost next to a listing of the same catalogue? | 1 VU, closed loop |
| `cart.js`       | What does a cart mutation cost?                                  | 5 carts/min       |
| `journey.js`    | How long does buying something take, end to end?                 | 8 journeys/min    |
| `rate-limit.js` | Does the write limiter hold, and what does holding cost?         | 10 VUs flooding   |

Run `smoke.js` first. It asserts behaviour rather than latency, so a failure
there means something is broken, not slow — and no number from the other
scripts is worth reading until it passes.

Each file's header says what it measures and why it is shaped the way it is.

## Knobs

All optional, all read from the environment (`bin/k6` forwards exactly these
and nothing else — a benchmark has no business reading the machine's `.env`).

| Variable         | Meaning                                                                                                                               |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `BASE_URL`       | The target. Defaults to `http://localhost:8099`, never a production host.                                                             |
| `BENCH_RATE`     | Arrival rate. Per **second** for `browse.js`/`capacity.js`, per **minute** for `cart.js`/`journey.js` — the limiter decides the unit. |
| `BENCH_DURATION` | Per stage, as a k6 duration (`30s`, `2m`).                                                                                            |
| `BENCH_VUS`      | Virtual users, for the two closed-loop scenarios.                                                                                     |
| `BENCH_COUPON`   | The coupon code to apply. Defaults to `WELCOME10`, which `DiscountDefaultsSeeder` creates.                                            |
| `BENCH_P95_*`    | Threshold overrides: `DOCUMENT`, `NAVIGATION`, `LISTING`, `SEARCH`, `WRITE`, `JOURNEY`, in milliseconds.                              |

Nothing is hardcoded about the catalogue. Every run starts by reading
`/sitemap.xml`, one product page and the checkout page to find its slugs,
variant ids and a country, so the same scripts measure the demo catalogue, a
scaled one and a real store's staging copy without being edited.

## Two request shapes, measured apart

Every storefront page answers in two ways, and they are tagged `kind`
separately because mixing them hides both:

- a **document** — the whole HTML page, which is what a first visit, a shared
  link and a crawler get. 45–55 KB.
- a **navigation** — the Inertia JSON for the next page, which is what every
  click after that gets. 1–5 KB, same controller, same queries.

Static assets are deliberately not fetched. k6 is not a browser, and images,
CSS and JS never reach PHP — FrankenPHP serves them, a CDN serves them in
production — so loading them would move the numbers without saying anything
about the application.

## What to run it against

Not `php artisan serve`. It is a development server with no cached config and
no useful opcache, so its numbers describe PHP's built-in server rather than
this application.

`docker-compose.bench.yml` stands up the production image (FrankenPHP,
opcache, `validate_timestamps=0`, the config/route/view/event caches its
entrypoint builds on startup) on port 8099, with a database of its own:

```sh
cp .env.example .env.bench      # then: APP_KEY (artisan key:generate --show),
                                # APP_ENV=production, APP_DEBUG=false,
                                # APP_URL=http://localhost:8099

B="docker compose -p lunar-bench -f docker-compose.prod.yml -f docker-compose.bench.yml"

$B build
$B run --rm app php artisan migrate --force
$B run --rm app php artisan lunar:create-admin --firstname=Bench --lastname=Admin \
    --email=bench@example.com --password=benchbench
$B run --rm app php artisan lunar:install --no-interaction

# One by one: plain `db:seed` runs DatabaseSeeder, which needs fakerphp -
# a dev dependency the production image does not have, by design (see
# docs/deployment.md). Every other seeder here is factory-free.
for s in DemoCatalogSeeder HeroSlideSeeder ContentPageSeeder ProductBundleSeeder \
         DiscountDefaultsSeeder ShippingDefaultsSeeder TaxDefaultsSeeder; do
    $B run --rm app php artisan db:seed --class=$s --force
done

$B up -d
BASE_URL=http://localhost:8099 ./bin/k6 run benchmarks/smoke.js
```

`-p lunar-bench` is not optional: without a project name of its own, compose
would reuse the prod stack's containers and database volume. Tear it down with
`$B down -v`.

The queue worker and the scheduler sit behind a `background` profile, so
`up -d` starts only the web service and the database — neither is on the
request path, and the scheduler would fire jobs in the middle of a
measurement. `$B --profile background up -d` brings them in deliberately.

## The write limiter will stop you

`throttle:storefront-write` allows **30 requests a minute per IP**, and a load
generator is one IP. That is why `cart.js` and `journey.js` count their rate
per minute, and why a single 429 fails them: `refused_writes` must be zero for
their latencies to mean anything.

To measure cart mutations under real load, raise the limiter on the benchmark
target — the limiters are declared in `AppServiceProvider::configureRateLimiting()`
— rather than raising `BENCH_RATE` and reading 429s as results. `rate-limit.js`
is the scenario that does want them.

## Reading the summary

- **Tagged lines** (`{ kind:document }`, `{ page:search }`) appear because a
  threshold names them. k6 only breaks out a sub-metric it has been given a
  threshold for.
- **`dropped_iterations` above zero** means k6 had no VU free when it was time
  to send a request, so the load generator decided the rate rather than the
  target. `browse.js`, `cart.js` and `journey.js` fail outright on it;
  `capacity.js` expects it at the top of the ramp.
- **`http_req_failed` in `rate-limit.js` is meaningless** — a 429 counts as a
  failure there, and 429 is the answer being measured.
- **Thresholds are a regression alarm, not an SLO.** The defaults are set with
  headroom over the reference machine below. Re-baseline them on yours: run
  `capacity.js`, then `browse.js` at a rate inside the flat part of the curve,
  and set `BENCH_P95_*` from what you get.

## A measured baseline

Everything below was measured on the production image through
`docker-compose.bench.yml`, on **4 cores / 7 GB sharing the machine with
forty other containers**. The absolute numbers are that machine's and yours
will differ; the _shapes_ are the application's and yours will not.

### Capacity, demo catalogue (7 products)

`browse.js` at a held arrival rate:

| Offered | Achieved | document p95 | navigation p95 |
| ------- | -------- | ------------ | -------------- |
| 4/s     | 2.8/s    | 218 ms       | 210 ms         |
| 8/s     | 5.6/s    | 221 ms       | 212 ms         |
| 12/s    | 7.9/s    | 732 ms       | 640 ms         |
| 25/s    | 15.3/s   | 5.12 s       | 5.15 s         |

Flat to 8/s, bending at 12, saturated at 25 (86 dropped iterations). Four
cores in classic FrankenPHP mode hold about ten requests a second here — and
_no request failed at any rate_, including the saturated one. That is the
number to weigh against the Dockerfile's note about worker mode.

### Search against a listing, by catalogue size

`search.js`, one VU, closed loop, catalogue grown with
`artisan bench:catalog`:

| Products | collection p95 | search p95 | ratio |
| -------- | -------------- | ---------- | ----- |
| 7        | 253 ms         | 273 ms     | 1.1×  |
| 250      | 452 ms         | 1.32 s     | 2.9×  |
| 1 000    | 456 ms         | 4.33 s     | 9.5×  |
| 2 007    | 464 ms         | 9.22 s     | 20×   |

The listing is flat; search grows straight in line with the catalogue, about
4.5 ms per product. This is `SCOUT_DRIVER=collection`, Scout's indexless
default, doing exactly what `LunarProductCatalog::search()` and
`.env.example` both warn about — loading the searchable catalogue into PHP and
matching substrings there on every request. A term that matches _nothing_
costs the same, because it has to look at everything before it can say so.

Two consequences worth knowing before you ship a real catalogue:

- At 2 007 products, `browse.js` at a gentle 4/s keeps a median navigation of
  208 ms but a p95 of **7.28 s** — ten percent of the mix is search, and it
  sets the percentile for the whole storefront.
- One search a second is enough to saturate the target at that size: the
  queue they build drags the listings down with them.

`SCOUT_DRIVER=meilisearch` plus `artisan scout:import` is a configuration
change, not a code change — `LunarProductCatalog` is written for it.

### Cart, checkout, and the limiter

| Measurement                                                    | Result                      |
| -------------------------------------------------------------- | --------------------------- |
| Cart mutation (add / update quantity / coupon on and off), p95 | 281 ms                      |
| A whole purchase, home → shipping chosen, 10 requests, p95     | 2.24 s                      |
| Writes accepted in a 30s flood from one IP                     | **exactly 30**, 604 refused |
| An accepted write under that flood, avg                        | 887 ms                      |
| A **refused** write under that flood, avg                      | 441 ms                      |

The last two lines are the point of `rate-limit.js`. `throttle:storefront-write`
is route middleware, so it runs _after_ the `web` group: by the time a request
is refused, Laravel has booted, decrypted the session cookie, read the session
row from Postgres and verified the CSRF token. A 429 costs about half an
accepted write — the limiter protects the cart, not the framework. At twenty
refusals a second that is real work being done for traffic already decided
against.

## Growing the catalogue

```sh
./bin/artisan bench:catalog --products=2000 --force
```

Generated products, deterministic names (so "Leather" matches a stable share
of them), prices spread across a range (so the price sort and filters have
something to do), always in stock. No images: a media conversion per product
costs minutes and changes nothing about the queries being measured — these
products carry no thumbnail, which is the one way they are not like the demo
ones.

It adds to the catalogue rather than replacing it, so run it twice to double
up. `--force` skips the confirmation it asks for when `APP_ENV=production`,
which the benchmark stack is.

## Scope

`journey.js` stops at choosing a shipping option. The step after it,
`POST /checkout/complete`, needs a Stripe payment intent, and a load test must
never be the thing that calls a payment provider a few thousand times.
`tests/Feature/Storefront/CheckoutTest.php` covers that step with Stripe
faked.

`smoke.js` is the only scenario worth putting in CI — single-threaded, a few
seconds, and it asserts behaviour rather than latency, which a shared CI
runner cannot measure meaningfully anyway. The rest want a quiet machine.
