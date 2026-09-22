# Deployment

Running this template in production. Back to the [README](../README.md).

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

