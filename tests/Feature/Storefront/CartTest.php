<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Cart;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the Cart context, end-to-end against a real
 * Lunar/PostgreSQL instance: adding, updating quantity, removing, and
 * computing totals (delegated to Lunar, we only verify it flows correctly
 * through to the Inertia page).
 */
final class CartTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_adding_a_product_creates_a_cart_line(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect();

        // $24.99 x 2 = $49.98, then the real 20% demo rate from
        // TaxDefaultsSeeder applies: $49.98 x 1.2 = $59.976, rounded to the
        // nearest cent by Lunar.
        $this->get(route('cart.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/cart')
                ->has('cart.lines', 1)
                ->where('cart.lines.0.quantity', 2)
                ->where('cart.lines.0.line_total.formatted', '$59.98')
                ->where('cart.total.formatted', '$59.98')
            );
    }

    public function test_updating_line_quantity_recalculates_totals(): void
    {
        $product = $this->createDemoProduct('T-shirt', 1000);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $lineId = $this->getCartLineId();

        $this->patch(route('cart.lines.update', $lineId), ['quantity' => 4])->assertRedirect();

        // $10.00 x 4 = $40.00, x 1.2 (20% demo rate) = $48.00.
        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines.0.quantity', 4)
                ->where('cart.total.formatted', '$48.00')
            );
    }

    public function test_adding_more_than_available_stock_fails_with_a_friendly_error(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 2);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->has('cart.lines', 0));
    }

    public function test_updating_quantity_beyond_stock_fails_with_a_friendly_error(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 2);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $lineId = $this->getCartLineId();

        $this->patch(route('cart.lines.update', $lineId), ['quantity' => 5])
            ->assertSessionHasErrors('quantity');

        // The line is untouched, not left in some partial state.
        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.quantity', 1));
    }

    public function test_the_shared_cart_item_count_reflects_the_cart_contents(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499);
        $variant = $this->firstVariant($product);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartItemCount', 0));

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variant->id, 'quantity' => 3]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('cartItemCount', 3));
    }

    public function test_browsing_without_adding_anything_never_creates_a_cart(): void
    {
        // The header badge is resolved on every single response, so it must
        // not be what brings `lunar.cart_session.auto_create` into play -
        // otherwise every visitor (and every dashboard page view) would
        // persist an empty cart row.
        $this->createDemoProduct('T-shirt', 2499);

        $this->get(route('home'))->assertOk();
        $this->get(route('dashboard'))->assertRedirect();

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_removing_a_line_empties_the_cart(): void
    {
        $product = $this->createDemoProduct('T-shirt', 1000);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $lineId = $this->getCartLineId();

        $this->delete(route('cart.lines.destroy', $lineId))->assertRedirect();

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('cart.lines', 0)
                ->where('cart.total.formatted', '$0.00')
            );
    }

    private function getCartLineId(): int
    {
        /** @var Cart $cart */
        $cart = Cart::latest()->first();

        return $cart->lines()->first()->id;
    }
}
