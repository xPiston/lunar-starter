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

The suite was written against the application as it stood, and then what it
found got fixed - in two passes, the second one reading query logs rather than
clocks. Every "before" below is measured, on the same box, the same image and
the same catalogue; none of them is an estimate.

### Capacity, demo catalogue (7 products)

`browse.js` at a held arrival rate, p95:

| Offered | document before | document after | navigation before | navigation after |
| ------- | --------------- | -------------- | ----------------- | ---------------- |
| 4/s     | 218 ms          | **180 ms**     | 210 ms            | **177 ms**       |
| 8/s     | 221 ms          | **180 ms**     | 212 ms            | **174 ms**       |
| 12/s    | 732 ms          | **185 ms**     | 640 ms            | **180 ms**       |
| 25/s    | 5.12 s          | **3.75 s**     | 5.15 s            | **3.71 s**       |

The knee moved from between 8 and 12 to beyond 12: what used to bend at
twelve requests a second is now as flat there as at four. What did _not_
move is the ceiling - 14.7 requests a second achieved against 25 offered,
against 15.3 before - so past that point the limit is not the queries. It is
four cores starting the application once per request, which is what classic
FrankenPHP mode means and what the Dockerfile's note about worker mode is
about.

No request failed at any rate, before or after, including the saturated one.

### Search, by catalogue size

`search.js`, one VU, closed loop, catalogue grown with
`artisan bench:catalog`. "Before" is Scout's `collection` engine, which has
no index and matches in PHP; "after" is the database query that replaced it
(see `LunarProductCatalog::search()`):

| Products | listing p95 | Scout `collection` | one query  | three unioned |
| -------- | ----------- | ------------------ | ---------- | ------------- |
| 7        | 253 ms      | 273 ms             | 273 ms     | 273 ms        |
| 250      | 452 ms      | 1.32 s             | —          | —             |
| 1 000    | 456 ms      | 4.33 s             | —          | —             |
| 2 007    | 464 ms      | 9.22 s             | **426 ms** | —             |
| 10 007   | 195 ms      | **78 s**, +142 MB  | 917 ms     | **274 ms**    |

Seventy-eight seconds, measured not extrapolated, for one search on ten
thousand products - and a term matching _nothing_ cost the same, because the
engine had to look at everything to find out. In practice that page was not
slow, it was dead: no timeout survives it.

Seventy-eight seconds became 917 ms by asking the database instead of PHP,
and 917 ms became **274 ms** by asking it three questions instead of one and
leaving the answer in SQL.
Both of the last two columns are a scan - an unanchored `LIKE` cannot use a
b-tree - so both are linear; the difference is what each row costs.

The one-query version matched attributes, SKUs and brand in a single `where`,
with the last two as `or whereHas(...)`. That puts a subplan probe inside the
row filter, so every one of ten thousand products was tested against all
three conditions. `explain (analyze)` on it, for a term matching a thousand
products: **522 ms**, a sequential scan over the products and another over all
20 015 variants. The same three conditions as a union of three selects, each
planned on its own: **41.9 ms**. Nothing was indexed, cached or skipped - the
work is the same work, asked in a shape the planner can do something with.

The second half of it is where the match set lives. The query used to be run
for its ids, which were then fetched into PHP and sent back as one bind each -
the same pattern the sitemap was cured of, and the same outcome, because the
list it carries is bounded by the catalogue rather than by the page. Left as a
subquery the page costs the same whatever the shopper typed:

| Term matching   | Ids fetched into PHP | As a subquery |
| --------------- | -------------------- | ------------- |
| 127 products    | **104 ms**           | 141 ms        |
| 10 007 products | 815 ms               | **133 ms**    |

A narrow term gives up 37 ms so that a broad one does not cost 815. The bind
parameters were not the problem, which is worth knowing because they look like
it: the same query with the ids inlined by `whereIntegerInRaw` instead of
bound - 6 parameters rather than 10 013 - cost 828 ms. Materialising the set
is the cost, whatever it then travels as. End to end, searching a term that
matches the entire catalogue now costs **272 ms**, against 281 ms for one
matching 127 products.

`paginate()` also stops after counting when nothing matched, rather than
asking for a page of a result it has just established is empty. On an indexed
listing that saves a round trip; on a query that carries a scan it halves the
page.

Ten thousand products is still roughly where a real engine stops being
optional, and switching to one is still only `SCOUT_DRIVER` plus
`artisan scout:import`. That path keeps the id list, because its ids come from
outside the database and there is no subquery to be had - it now inlines them
rather than binding one each, which is the 65 535-parameter wall avoided for
the price of a longer statement.

#### The trigram index that was not shipped

With the union in place, `pg_trgm` was the obvious next step and it was
measured rather than assumed. A GIN index on `attribute_data::text` plus one
on `lunar_product_variants.sku`, with the search's attribute branch given a
whole-document `ilike` as an index-backed prefilter:

| Shape                                     | Id query, 1 000 matches |
| ----------------------------------------- | ----------------------- |
| One `where`, `or whereHas` (what shipped) | 522 ms                  |
| Three unions, no index                    | **41.9 ms**             |
| Three unions + prefilter, no index        | 65.6 ms                 |
| Three unions + prefilter + GIN trigram    | **9.5 ms**              |

The index works - 9.5 ms, and the SKU branch drops from 30 ms to 0.03 ms. It
was still left out, on these numbers. It buys 32 ms on a page that now costs
324 ms, in exchange for the `pg_trgm` extension (which a managed Postgres may
not grant), **3.4 MB of index against a 3.8 MB table**, a write amplified on
every product save, and a Postgres-only migration in a template whose query
layer is otherwise driver-agnostic. And the prefilter it needs makes the query
_slower_ whenever the index cannot serve it: a term under three characters
builds no trigram, so Postgres scans - 64 ms with the prefilter against 41.9
without.

The shape the planner likes is free and portable. The index is neither, and
at the catalogue size where 32 ms would start to matter, the documented answer
is already a real search engine.

### What a query log found that the clock did not

The rest came from reading the queries behind a single page rather than
timing it. All measured at 10 007 products:

| Measurement                                           | Before                | After      |
| ----------------------------------------------------- | --------------------- | ---------- |
| Queries for one 12-product collection page            | **106**               | **10**     |
| That page                                             | 258 ms                | **67 ms**  |
| `?sort=price_asc` on a 2 000-product collection       | **5.35 s**            | 280 ms     |
| `/sitemap.xml`                                        | 1.08 s                | **348 ms** |
| Widest query behind the sitemap                       | **10 007 parameters** | 8          |
| Queries for the navbar's collection list, per request | 8                     | 2          |
| Queries for the home page                             | 16                    | **14**     |

The 106 queries were an N+1 hiding under a page that looked fine, because
`ProductMapper::toSummary()` reads `defaultUrl`, `thumbnail` and each
variant's price and none of the three was eager-loaded. The last of them is
worth knowing about: Lunar casts the `price` column through a cast that reads
`$model->priceable->unit_quantity`, so merely _reading_ a price loads its
variant back, one row at a time. That one survived the first pass and was
caught by `QueryBudgetTest`, which asserts a listing's query count does not
grow with the number of products on the page.

The price sort was a correlated subquery, so the database re-ran `min(price)`
for every candidate product. Aggregating the price table once and joining it
is the same answer in one pass.

The home page's two were the navbar's list being read **twice** in one
request: once by `HandleInertiaRequests` for the navigation and once by
`HomeController` for its own collection tiles, each costing the collections
and their slugs. Neither caller is wrong and neither can see the other, so
the memo sits in the adapter, which is where the question is asked - and the
port is bound `scoped` rather than `bind` so that both callers get the same
one. `scoped` and not `singleton`: the published set depends on the channel,
so a worker-mode runtime has to flush it between requests.

This one is worth noticing for what it says about the tests that were meant
to catch it. `QueryBudgetTest` compares a 3-product page against a
12-product one, and a duplication that is constant in the number of products
passes that comparison perfectly while the page does twice the work. The
budget now also asserts that the navigation is read once, and that an empty
page stays within the cost of its chrome.

That chrome is the floor under every page in the shop, and it was measured
too: six queries before the page itself is considered - the session read, the
navbar's two, the footer's editorial pages, the flag that says whether there
are bundles to link to, and the session write. The two session queries only
appear under the drivers production actually uses; `phpunit.xml` runs the
suite on `array`, so `QueryBudgetTest` now switches itself to `database` -
otherwise it measures a visitor who does not exist. The database **cache**
store, measured in the same place, costs nothing at all on a storefront
request: nothing on the storefront reads the cache.

Switching those drivers on inside a test has one trap in it, and it cost a
run to find. Laravel garbage-collects the session table by **lottery** -
`[2, 100]` by default, so two requests in a hundred also run a
`delete from sessions`. A query budget left to that fails roughly one run in
seven with one query more than it asked for, which is the most corrosive
thing a test can do: nobody trusts it, so its number gets raised until it
asserts nothing. The budget disables the lottery and says why. The four always-on
queries that would be a cache's whole purpose are read from Postgres on every
single page view.

The sitemap looked like a case for chunked index files - its own controller
said so - and it was not. Generating 10 022 URLs costs 133 ms and serialising
them 8 ms, both measured; the second was spent fetching every published
product id into PHP and sending all ten thousand back as bind parameters.
Left as a subquery it is one query, eight parameters, 24 ms. The sitemap is
still one document, which the spec allows up to 50 000 URLs.

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
accepted write - the limiter protects the cart, not the framework. At twenty
refusals a second that is real work being done for traffic already decided
against.

Moving the limiter ahead of the session is the obvious fix and it is the wrong
one, for three reasons worth writing down so nobody has to find them twice.
`Middleware::prependToPriorityList()` cannot do it - `ThrottleRequests` is
already in Laravel's priority list, and the merge skips any middleware already
present - so it would mean replacing the whole list and pinning framework
internals into `bootstrap/app.php`. And the order is deliberate:
`ThrottleRequests::resolveRequestSignature()` keys on `$request->user()` when
there is one, which is why Laravel runs it after the session and auth.
`routes/auth.php` has two `throttle:6,1` routes inside `Route::middleware('auth')`,
so the reorder would silently re-key them from per-user to per-IP and put a
whole office behind one shared budget. The cost of a refused request is real;
at the scale where it matters, the answer is in front of the application rather
than inside it.

### What is still the tail

At 10 007 products, `browse.js` at 8/s gives a document p95 of **275 ms** and
a navigation p95 of **275 ms**. Three runs, because one sample is not a
measurement: 275, 275, 275 ms on the document side and 272, 272, 280 ms on the
navigation side. The two sides meeting is the news: the navigation p95 used to
be search, and search is no longer the tail.

Nothing is dropped, no request fails, and **every threshold passes** -
including `BENCH_P95_NAVIGATION` at 400 ms, which an earlier edition of this
file said could not pass on a catalogue this size. It was the search scan that
made that true, and the search scan is no longer what it was.

What is left is not a query. The health route `/up` boots the framework and
every Lunar service provider, then answers without touching the database or
the session: **76 ms**, twelve samples, 73.7 to 81.4. The home page on the
same stack costs 169 ms. So **45% of the cheapest real page in the shop is the
application starting up**, and that floor is under every request, including
the ones the rate limiter refuses.

Classic FrankenPHP mode is what makes it a floor: every request builds the
application from nothing, which is also what makes the mode safe to reuse
without auditing a line of code for state that leaks between requests. Worker
mode removes the 76 ms and asks for that audit in exchange - singletons, the
Lunar storefront session, the Stripe client, the cart. It is the one remaining
change on this list with the size of an architecture decision rather than a
fix, which is why the `Dockerfile` names it and does not take it.

Two smaller things were measured and left alone. A listing loads its variants
twice - once as `variants`, once as `variants.prices.priceable`, because
Lunar's price cast reads the variant back off each price and Eloquent cannot
know it is the same row it already has; that is one query, not one per
product. And the four always-on chrome queries are never cached, on a store
where `CACHE_STORE=database` is configured and unused: caching them is worth
four queries on every page, and it needs an invalidation story (a shop owner
renaming a collection expects the navbar to follow) rather than a TTL picked
to make a benchmark look good.

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
