<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Checkout\CheckoutFailedException;
use App\Domain\Checkout\CheckoutState;
use App\Domain\Checkout\Order;
use App\Domain\Checkout\PaymentIntent;
use App\Domain\Checkout\Port\CheckoutGateway;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Country;
use Lunar\Models\Order as LunarOrder;
use Lunar\Stripe\Facades\Stripe;

/**
 * Outbound adapter: Lunar (+ Stripe) implementation of the CheckoutGateway
 * port.
 *
 * Only file (along with this folder's mappers) that imports
 * `Lunar\*`/`Lunar\Stripe\*` code: replacing Stripe with another PSP, or
 * Lunar with another engine, means rewriting this class (and the mappers)
 * so it keeps honoring CheckoutGateway.
 */
final class LunarCheckoutGateway implements CheckoutGateway
{
    public function __construct(
        private readonly AddressMapper $addresses,
        private readonly ShippingOptionMapper $shippingOptions,
        private readonly OrderMapper $orders,
    ) {}

    public function listCountries(): array
    {
        return Country::orderBy('name')
            ->get()
            ->map($this->addresses->countryToDomain(...))
            ->all();
    }

    public function listShippingOptions(): array
    {
        $cart = CartSession::current();

        return ShippingManifest::getOptions($cart)
            ->map($this->shippingOptions->toDomain(...))
            ->all();
    }

    public function currentState(): CheckoutState
    {
        $cart = CartSession::current();

        return new CheckoutState(
            billingAddress: $cart->billingAddress ? $this->addresses->toDomain($cart->billingAddress) : null,
            shippingAddress: $cart->shippingAddress ? $this->addresses->toDomain($cart->shippingAddress) : null,
            selectedShippingOption: $cart->shippingAddress?->shipping_option,
        );
    }

    public function setBillingAddress(array $address): void
    {
        CartSession::current()->setBillingAddress($address);
    }

    public function setShippingAddress(array $address): void
    {
        CartSession::current()->setShippingAddress($address);
    }

    public function selectShippingOption(string $identifier): void
    {
        $cart = CartSession::current();

        if (! $cart->shippingAddress) {
            throw new CheckoutFailedException(
                'Please provide a shipping address before choosing a shipping method.'
            );
        }

        $option = ShippingManifest::getOption($cart, $identifier);

        if (! $option) {
            throw new CheckoutFailedException("Unknown shipping method: \"{$identifier}\".");
        }

        $cart->setShippingOption($option);
    }

    public function startPayment(): PaymentIntent
    {
        $intent = Stripe::fetchOrCreateIntent(CartSession::current());

        return new PaymentIntent(
            clientSecret: (string) $intent->client_secret,
            publishableKey: (string) config('services.stripe.public_key'),
        );
    }

    public function completePayment(string $paymentIntentId): Order
    {
        $result = Payments::driver('stripe')
            ->cart(CartSession::current())
            ->withData(['payment_intent' => $paymentIntentId])
            ->authorize();

        if (! $result->success || ! $result->orderId) {
            throw new CheckoutFailedException($result->message ?: 'Payment failed.');
        }

        return $this->orders->toDomain(LunarOrder::findOrFail($result->orderId));
    }
}
