<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * `php artisan config:cache` has to work, because the production container
 * runs it on every start (see frankenphp/docker-entrypoint.sh) and exits if
 * it fails. It stopped working once before: `AppServiceProvider::boot()` puts
 * a closure into `lunar.cart.eager_load` (a `morphWith`, so a cart holding a
 * bundle doesn't try to load a product variant's relations on it), and
 * `config:cache` serialises the config repository with `var_export` after
 * booting the providers - which cannot write a closure and dies on it.
 *
 * Nothing in the rest of the suite notices: the application under test never
 * caches its config. So this test runs the real command in a real subprocess,
 * because that is the only way to get the real `$_SERVER['argv']` the guard in
 * AppServiceProvider reads.
 */
class ConfigCacheTest extends TestCase
{
    /**
     * Writes bootstrap/cache/config.php, which every later process would then
     * read instead of the environment it was started with - including a
     * developer's dev server. Cleared whatever happens.
     */
    protected function tearDown(): void
    {
        Process::path(base_path())->run([PHP_BINARY, 'artisan', 'config:clear']);

        parent::tearDown();
    }

    public function test_the_configuration_can_be_cached()
    {
        $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'config:cache']);

        $this->assertTrue(
            $result->successful(),
            'config:cache failed, so the production container would not start: '.$result->errorOutput().$result->output(),
        );
        $this->assertFileExists(base_path('bootstrap/cache/config.php'));
    }

    /**
     * `optimize` is the other way in - it runs config:cache itself, and it is
     * what most deployment guides reach for.
     */
    public function test_the_application_can_be_optimized()
    {
        $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'optimize']);

        $this->assertTrue(
            $result->successful(),
            'artisan optimize failed: '.$result->errorOutput().$result->output(),
        );
    }
}
