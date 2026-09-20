<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * Proves the rate limiters registered in AppServiceProvider are actually
 * wired to the routes, not just declared - a limiter that exists but isn't
 * attached anywhere is worse than none (false sense of protection).
 */
final class RateLimitingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_cart_mutations_are_rate_limited(): void
    {
        $product = $this->createDemoProduct();
        $variant = $this->firstVariant($product);

        for ($i = 0; $i < 30; $i++) {
            $this->post(route('cart.lines.store'), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])->assertRedirect();
        }

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertStatus(429);
    }

    public function test_checkout_completion_is_rate_limited(): void
    {
        // The throttle middleware runs before controller validation, so
        // these can be malformed requests - only the request count matters.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('checkout.complete'), []);
        }

        $this->post(route('checkout.complete'), [])->assertStatus(429);
    }

    public function test_guest_order_lookup_is_rate_limited(): void
    {
        // Same reasoning as checkout completion: a two-field guessing
        // surface (reference + email), so malformed requests are enough.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('orders.lookup.find'), []);
        }

        $this->post(route('orders.lookup.find'), [])->assertStatus(429);
    }
}
