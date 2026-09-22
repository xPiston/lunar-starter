#!/bin/sh
set -e

# Only refresh Laravel's caches (config/routes/views/events) when the
# container is actually starting the web server - not for a one-off use of
# the same image (`docker run <image> php artisan migrate`,
# `... artisan queue:work`, etc.) where it would be pointless, or even
# harmful if not all the env vars needed for that task are injected.
#
# Deliberately NO `php artisan migrate` here: running migrations from the
# web container's entrypoint is risky as soon as there are multiple
# replicas (several concurrent migrations on startup) - see the README to
# run it as a separate, one-off deployment step instead.
if [ "$1" = "frankenphp" ]; then
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache
fi

exec "$@"
