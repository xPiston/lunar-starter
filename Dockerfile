#syntax=docker/dockerfile:1

# PROD image, distinct from docker/php/Dockerfile.dev (local tooling only).
# CLASSIC FrankenPHP mode (no worker/Octane): every request cleanly restarts
# the app, same behavior as `php artisan serve`/php-fpm - no risk of state
# leaking between requests (singletons, Lunar cart, Stripe session...) to
# audit for. See the README for the worker mode path if performance becomes
# a real concern later.

# ---- Frontend (Vite/React) ------------------------------------------------
FROM node:24-bookworm-slim AS frontend_builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Base FrankenPHP (PHP extensions required by Lunar) -------------------
FROM dunglas/frankenphp:1.12.7-php8.5 AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]
WORKDIR /app

# Same extensions as docker/php/Dockerfile.dev (dev/prod must stay in sync):
# Lunar (pdo_pgsql/intl/bcmath/exif/gd/zip), pcntl for queues, opcache for
# performance.
RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends file git
	install-php-extensions \
		@composer \
		pdo_pgsql \
		pgsql \
		intl \
		bcmath \
		exif \
		gd \
		zip \
		pcntl \
		opcache
	rm -rf /var/lib/apt/lists/*
EOF

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":${PHP_INI_DIR}/app.conf.d"

COPY --link frankenphp/conf.d/app.ini $PHP_INI_DIR/app.conf.d/
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

# /metrics (Caddy's admin API, port 2019 local to the container) responds
# as soon as FrankenPHP has started - a simple, reliable probe, with no
# dependency on an application route or on installing curl in the image.
HEALTHCHECK --start-period=30s --interval=30s --timeout=5s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# ---- Production image ------------------------------------------------------
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Composer alone first (without the rest of the code): the Docker cache
# only re-downloads dependencies when composer.json/composer.lock change,
# not on every application code change.
COPY --link composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-interaction --prefer-dist --no-progress

COPY --link . .
COPY --link --from=frontend_builder /app/public/build public/build

# Caches must exist BEFORE any artisan/composer command: the Blade view
# compiler (indirectly triggered by package:discover, which boots the
# framework) writes to storage/framework/views on the very first view
# resolution, otherwise "Please provide a valid cache path".
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache

# --classmap-authoritative (faster, no on-the-fly PSR-4 fallback) + composer
# scripts (post-autoload-dump -> package:discover) run here, once the
# application code is in place.
RUN <<-EOF
	composer dump-autoload --classmap-authoritative --no-dev
	php artisan storage:link
	# Filament assets (admin JS/CSS) published at image build time rather
	# than at runtime: `lunar:install` calls `filament:assets` itself, but a
	# production image should be immutable - this is already done by the
	# time `lunar:install` runs in production.
	php artisan filament:assets
	chown -R www-data:www-data storage bootstrap/cache public
	# g=u: arbitrary-UID runtimes (OpenShift, some PaaS) run with a random
	# UID but the group stays www-data (GID 0 possible) - aligning group
	# permissions with the owner's avoids being blocked on writes there.
	# public/ stays included (not just storage/bootstrap): `lunar:install`
	# republishes Filament assets on every run (implicit --force), even if
	# the image already has them.
	chmod -R g=u storage bootstrap/cache public
	# Caddy (running as www-data, not root) needs to be able to write its
	# state (config autosave, TLS management) under /data and /config -
	# without this it still starts but spams permission errors on every
	# request/lock.
	mkdir -p /data/caddy /config/caddy
	chown -R www-data:www-data /data /config
EOF

USER www-data
