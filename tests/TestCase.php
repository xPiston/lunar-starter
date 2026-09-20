<?php

namespace Tests;

use Database\Seeders\LunarDefaultsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Any test database refreshed by RefreshDatabase must have Lunar's base
     * data (channel, currency, tax zone...), or even a simple login
     * (Lunar\Listeners\CartSessionAuthListener) crashes. See
     * LunarDefaultsSeeder for the details.
     */
    protected $seed = true;

    protected $seeder = LunarDefaultsSeeder::class;
}
