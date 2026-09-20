<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for guest order lookup: proves a guest can find their own
 * order by reference + checkout email, and - just as importantly - that a
 * wrong guess on either field reveals nothing (same generic error, no
 * distinction between "no such reference" and "wrong email").
 */
final class GuestOrderLookupTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_guest_can_find_their_order_by_reference_and_email(): void
    {
        $order = $this->placeGuestOrder();

        $this->post(route('orders.lookup.find'), [
            'reference' => $order->reference,
            'email' => 'ada@example.com',
        ])->assertRedirect(route('orders.lookup.result'));

        $this->get(route('orders.lookup.result'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/orders/lookup-result')
                ->where('order.reference', $order->reference)
                ->has('order.lines', 1)
            );
    }

    public function test_the_wrong_email_is_rejected_with_a_generic_error(): void
    {
        $order = $this->placeGuestOrder();

        $this->post(route('orders.lookup.find'), [
            'reference' => $order->reference,
            'email' => 'not-the-owner@example.com',
        ])->assertSessionHasErrors('lookup');
    }

    public function test_an_unknown_reference_gets_the_same_generic_error_as_a_wrong_email(): void
    {
        $order = $this->placeGuestOrder();

        // Same exact message for "no such reference" and "wrong email" -
        // no information leak about which field was wrong.
        $message = 'No order found matching that reference and email.';

        $this->post(route('orders.lookup.find'), [
            'reference' => '99999999',
            'email' => 'ada@example.com',
        ])->assertSessionHasErrors(['lookup' => $message]);

        $this->post(route('orders.lookup.find'), [
            'reference' => $order->reference,
            'email' => 'someone-else@example.com',
        ])->assertSessionHasErrors(['lookup' => $message]);
    }

    public function test_the_result_page_cannot_be_reached_without_a_successful_lookup(): void
    {
        $this->get(route('orders.lookup.result'))->assertRedirect(route('orders.lookup'));
    }

    private function placeGuestOrder(): Order
    {
        config(['lunar.stripe.allow_partial_payment' => true]);
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();

        $product = $this->createDemoProduct('T-shirt', 2499);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variant->id, 'quantity' => 1]);

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

        return Order::whereNull('user_id')->latest('id')->firstOrFail();
    }
}
