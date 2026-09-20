<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Mail\OrderConfirmationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Country;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the Checkout context: address -> shipping -> payment,
 * end-to-end against a real Lunar/PostgreSQL instance. Stripe payment is
 * simulated via `Lunar\Stripe\MockClient` (provided by the package) - no
 * real network call to the Stripe API.
 */
final class CheckoutTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_address_can_be_set_and_is_reflected_in_checkout_state(): void
    {
        $this->addProductToCart();

        $this->postAddress()->assertRedirect();

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/checkout')
                ->where('checkout.state.billing_address.first_name', 'Ada')
                ->where('checkout.state.shipping_address.city', 'London')
                // Standard + Express, both on the "Worldwide" (unrestricted)
                // zone - see database/seeders/ShippingDefaultsSeeder.php.
                ->has('checkout.shipping_options', 2)
                ->where('checkout.shipping_options.0.identifier', 'standard')
            );
    }

    public function test_selecting_a_shipping_option_without_an_address_fails(): void
    {
        $this->addProductToCart();

        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard'])
            ->assertRedirect()
            ->assertSessionHasErrors('identifier');
    }

    public function test_shipping_option_selection_makes_the_checkout_payment_ready(): void
    {
        Stripe::fake();

        $this->addProductToCart();
        $this->postAddress();

        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard'])
            ->assertRedirect();

        $this->get(route('checkout.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('checkout.state.selected_shipping_option', 'standard')
                ->where('checkout.payment_intent.publishable_key', config('services.stripe.public_key'))
                ->has('checkout.payment_intent.client_secret')
            );
    }

    public function test_completing_payment_creates_an_order_and_shows_the_confirmation_page(): void
    {
        // The MockClient fixtures don't carry the test cart's exact total
        // (see vendor/lunarphp/stripe/resources/responses/*.json): disabling
        // the strict amount/currency check is the package's own choice for
        // this test scenario (cf. StripePaymentType::assertIntentMatchesTotal).
        config(['lunar.stripe.allow_partial_payment' => true]);
        // Prevents Lunar\Stripe\Actions\StoreAddressInformation from
        // overwriting the test addresses with the Stripe fixtures' fake ones.
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();
        Mail::fake();

        $this->addProductToCart();
        $this->postAddress();
        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard']);

        // "PI_CAPTURE" is the special identifier MockClient recognizes to
        // simulate an already-succeeded Stripe PaymentIntent (see
        // vendor/lunarphp/stripe/src/MockClient.php).
        $this->post(route('checkout.complete'), ['payment_intent' => 'PI_CAPTURE'])
            ->assertRedirect(route('checkout.confirmation'));

        $this->get(route('checkout.confirmation'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/checkout-confirmation')
                ->where('order.placed', true)
                ->has('order.lines', 1)
                ->where('order.lines.0.quantity', 2)
            );

        Mail::assertQueued(
            OrderConfirmationMail::class,
            fn (OrderConfirmationMail $mail) => $mail->hasTo('ada@example.com') && $mail->order->placed,
        );
    }

    private function addProductToCart(): void
    {
        $product = $this->createDemoProduct('T-shirt', 2499);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    private function postAddress(): TestResponse
    {
        $countryId = Country::first()->id;

        $address = [
            'country_id' => $countryId,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line_one' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'contact_email' => 'ada@example.com',
        ];

        return $this->post(route('checkout.address'), [
            'billing' => $address,
            'shipping' => $address,
        ]);
    }
}
