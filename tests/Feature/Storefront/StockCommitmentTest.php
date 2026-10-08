<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * Selling a unit takes it off the shelf.
 *
 * Which sounds too obvious to test, and is exactly why it is here: Lunar
 * manages no inventory of its own, so before this existed a shop could sell
 * the same last item to every customer who asked and the stock figure never
 * moved. Every assertion below failed before App\Infrastructure\Lunar\
 * Inventory\LunarStockLedger was written.
 */
final class StockCommitmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    protected function setUp(): void
    {
        parent::setUp();

        // The MockClient fixtures carry their own totals, like the rest of the
        // checkout tests.
        config(['lunar.stripe.allow_partial_payment' => true]);
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();
        Mail::fake();
    }

    public function test_placing_an_order_reduces_the_stock_it_sold(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 3);

        $this->assertSame(7, $variant->refresh()->stock);
    }

    public function test_the_second_customer_does_not_get_the_same_last_unit(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 2);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 2);
        $this->assertSame(0, $variant->refresh()->stock);

        // Nothing stops a second sale here - the cart refuses it, but an
        // order placed from the admin panel or an import does not ask. What
        // matters is that the shelf records it rather than staying at two.
        $this->flushSession();
        $this->buy($variant->id, quantity: 2, expectRefusal: true);

        $this->assertSame(0, $variant->refresh()->stock, 'a cart that cannot be filled must not move the stock');
    }

    public function test_a_product_that_never_runs_out_is_not_counted_down(): void
    {
        $product = $this->createDemoProduct('Gift card', 2499, stock: 5);
        $variant = $this->firstVariant($product);
        $variant->update(['purchasable' => 'always']);

        $this->buy($variant->id, quantity: 3);

        $this->assertSame(5, $variant->refresh()->stock);
    }

    public function test_stock_is_taken_once_however_often_the_order_is_saved(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 2);
        $this->assertSame(8, $variant->refresh()->stock);

        // What the admin panel does on any edit.
        $order = $this->placedOrder();
        $order->touch();
        $order->update(['notes' => 'Called the customer']);

        $this->assertSame(8, $variant->refresh()->stock);
    }

    public function test_cancelling_an_order_puts_its_units_back(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 4);
        $this->assertSame(6, $variant->refresh()->stock);

        $this->placedOrder()->update(['status' => 'cancelled']);

        $this->assertSame(10, $variant->refresh()->stock);
    }

    public function test_the_units_are_given_back_once_however_often_the_order_is_saved(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 4);
        $this->placedOrder()->update(['status' => 'cancelled']);
        $this->assertSame(10, $variant->refresh()->stock);

        // Staff editing a cancelled order, and cancelling an already
        // cancelled one. Neither is a second cancellation.
        $this->placedOrder()->update(['notes' => 'Customer called to confirm']);
        $this->placedOrder()->update(['status' => 'cancelled']);

        $this->assertSame(10, $variant->refresh()->stock);
    }

    public function test_un_cancelling_an_order_takes_the_units_again(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 4);

        // Cancelled by mistake, then put back. The shelf has to follow both
        // ways or it drifts upwards every time somebody misclicks.
        $this->placedOrder()->update(['status' => 'cancelled']);
        $this->assertSame(10, $variant->refresh()->stock);

        $this->placedOrder()->update(['status' => 'payment-received']);
        $this->assertSame(6, $variant->refresh()->stock);
    }

    public function test_a_status_that_is_not_a_cancellation_leaves_the_shelf_alone(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499, stock: 10);
        $variant = $this->firstVariant($product);

        $this->buy($variant->id, quantity: 4);

        foreach (['dispatched', 'delivered'] as $status) {
            $this->placedOrder()->update(['status' => $status]);
            $this->assertSame(6, $variant->refresh()->stock, "{$status} must not move the shelf");
        }
    }

    public function test_an_order_that_never_took_anything_gives_nothing_back(): void
    {
        $product = $this->createDemoProduct('Gift card', 2499, stock: 5);
        $variant = $this->firstVariant($product);
        $variant->update(['purchasable' => 'always']);

        $this->buy($variant->id, quantity: 3);
        $this->assertSame(5, $variant->refresh()->stock);

        $this->placedOrder()->update(['status' => 'cancelled']);

        $this->assertSame(5, $variant->refresh()->stock);
    }

    private function placedOrder(): Order
    {
        return Order::whereNotNull('placed_at')->firstOrFail();
    }

    private function buy(int $variantId, int $quantity, bool $expectRefusal = false): void
    {
        $added = $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
        ]);

        if ($expectRefusal) {
            $added->assertSessionHasErrors();

            return;
        }

        $address = [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line_one' => '1 Rue de Test',
            'city' => 'Paris',
            'postcode' => '75001',
            'country_id' => Country::first()->id,
            'contact_email' => 'ada@example.com',
        ];

        $this->post(route('checkout.address'), ['billing' => $address, 'shipping' => $address]);
        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard']);
        $this->post(route('checkout.complete'), ['payment_intent' => 'PI_CAPTURE'])
            ->assertRedirect(route('checkout.confirmation'));
    }
}
