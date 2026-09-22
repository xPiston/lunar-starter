<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for order history: proves an order placed while logged in
 * is linked to that account (via Lunar\Base\Traits\LunarUser +
 * Lunar\Listeners\CartSessionAuthListener - nothing this template built),
 * and that App\Domain\Account\Port\OrderHistory actually scopes to the
 * current user rather than trusting the URL.
 */
final class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('account.orders'))->assertRedirect(route('login'));
    }

    public function test_a_user_with_no_orders_sees_an_empty_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/orders/index')
                ->has('orders', 0)
            );
    }

    public function test_a_placed_order_appears_in_the_owners_history_with_full_detail(): void
    {
        $order = $this->placeOrderAs($user = User::factory()->create());

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/orders/index')
                ->has('orders', 1)
                ->where('orders.0.reference', $order->reference)
                ->where('orders.0.item_count', 2)
            );

        $this->actingAs($user)
            ->get(route('account.orders.show', $order->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/account/orders/show')
                ->where('order.reference', $order->reference)
                ->has('order.lines', 1)
                ->where('order.shipping_address.city', 'London')
            );
    }

    public function test_a_user_cannot_view_another_users_order(): void
    {
        $order = $this->placeOrderAs(User::factory()->create());
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('account.orders.show', $order->reference))
            ->assertNotFound();
    }

    private function placeOrderAs(User $user): Order
    {
        config(['lunar.stripe.allow_partial_payment' => true]);
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();

        $this->actingAs($user);

        $product = $this->createDemoProduct('T-shirt', 2499);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $address = [
            'country_id' => Country::first()->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line_one' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'contact_email' => 'ada@example.com',
        ];

        $this->post(route('checkout.address'), ['billing' => $address, 'shipping' => $address]);
        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard']);
        $this->post(route('checkout.complete'), ['payment_intent' => 'PI_CAPTURE']);

        return Order::whereUserId($user->id)->firstOrFail();
    }
}
