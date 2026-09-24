<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for what a customer hears after buying.
 *
 * The risk this guards against is not silence, it is noise: a shop that adds
 * an internal status to Lunar's config must not start emailing customers
 * about its own bookkeeping, and an order saved twice must not send the same
 * update twice.
 */
final class OrderStatusNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_a_customer_is_told_when_their_order_ships(): void
    {
        $order = $this->placeOrder();

        $order->update(['status' => 'dispatched']);

        Mail::assertQueued(
            OrderStatusUpdatedMail::class,
            fn (OrderStatusUpdatedMail $mail): bool => $mail->hasTo('ada@example.com')
                && $mail->change->reference === $order->reference
                && $mail->change->status->handle === 'dispatched'
        );
    }

    /**
     * The admin panel saves the whole order on any edit. Only a status that
     * actually moved may send anything.
     */
    public function test_saving_an_order_without_moving_it_sends_nothing(): void
    {
        $order = $this->placeOrder();

        $order->update(['status' => 'dispatched']);
        Mail::assertQueuedCount(1);

        $order->update(['status' => 'dispatched']);
        $order->update(['notes' => 'Left with a neighbour.']);

        Mail::assertQueuedCount(1);
    }

    /**
     * An allow-list, not "announce every change": a shop's own statuses are
     * not customer-facing, and the wording of an email is not something to
     * derive from a slug.
     */
    public function test_a_status_the_shop_did_not_configure_stays_internal(): void
    {
        $order = $this->placeOrder();

        $order->update(['status' => 'awaiting-stock']);

        Mail::assertNothingQueued();
    }

    /**
     * payment-received is deliberately absent from the configured statuses:
     * the order confirmation already covers that moment, and two emails a
     * minute apart read as a bug.
     */
    public function test_payment_received_does_not_duplicate_the_confirmation(): void
    {
        $order = $this->placeOrder();

        $order->update(['status' => 'payment-received']);

        Mail::assertNothingQueued();
    }

    public function test_the_feature_can_be_switched_off_entirely(): void
    {
        config(['order_notifications.enabled' => false]);
        $order = $this->placeOrder();

        $order->update(['status' => 'dispatched']);

        Mail::assertNothingQueued();
    }

    /**
     * A cart that never completed checkout has nobody waiting on it, and its
     * address may be half-typed.
     */
    public function test_an_order_that_was_never_placed_notifies_nobody(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['placed_at' => null])->saveQuietly();

        $order->update(['status' => 'dispatched']);

        Mail::assertNothingQueued();
    }

    public function test_the_order_page_shows_where_the_order_has_got_to(): void
    {
        $user = User::factory()->create();
        $order = $this->placeOrder($user);
        $order->update(['status' => 'dispatched']);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status.handle', 'dispatched')
                ->where('order.status.label', 'Dispatched')
            );
    }

    private function placeOrder(?User $user = null): Order
    {
        config(['lunar.stripe.allow_partial_payment' => true]);
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();

        $this->actingAs($user ?? User::factory()->create());

        $product = $this->createDemoProduct('T-shirt', 2499);
        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $this->firstVariant($product)->id,
            'quantity' => 1,
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

        // The confirmation mail is part of placing an order, not of this
        // feature: cleared so each test counts only what it triggered.
        Mail::fake();

        return Order::latest('id')->firstOrFail();
    }
}
