<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Cart\ViewCart;
use App\Application\Checkout\CompleteCheckout;
use App\Application\Checkout\SelectShippingOption;
use App\Application\Checkout\SetBillingAddress;
use App\Application\Checkout\SetShippingAddress;
use App\Application\Checkout\ShowCheckout;
use App\Domain\Checkout\CheckoutFailedException;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutController extends Controller
{
    /**
     * Mirrors `Lunar\Validation\Cart\ValidateCartForOrderCreation::getAddressRules()`
     * (country_id/first_name/line_one/city/postcode required), extended with
     * the optional fields the form also collects.
     */
    private const ADDRESS_RULES = [
        'country_id' => ['required', 'integer'],
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['nullable', 'string', 'max:255'],
        'company_name' => ['nullable', 'string', 'max:255'],
        'line_one' => ['required', 'string', 'max:255'],
        'line_two' => ['nullable', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:255'],
        'state' => ['nullable', 'string', 'max:255'],
        'postcode' => ['required', 'string', 'max:32'],
        'contact_email' => ['nullable', 'email', 'max:255'],
        'contact_phone' => ['nullable', 'string', 'max:32'],
    ];

    public function show(ShowCheckout $showCheckout, ViewCart $viewCart): Response
    {
        return Inertia::render('storefront/checkout', [
            'cart' => $viewCart->handle()->toArray(),
            'checkout' => $showCheckout->handle()->toArray(),
            'meta' => (new PageMeta(title: 'Checkout', description: 'Complete your order.', noindex: true))->toArray(),
        ]);
    }

    /**
     * A single address step (billing + shipping), rather than two separate
     * forms on two pages: that's what nearly every real checkout does. The
     * "same as billing" checkbox is handled on the frontend (it copies the
     * fields) - the backend always receives both addresses fully filled in,
     * no conditional validation.
     */
    public function updateAddress(
        Request $request,
        SetBillingAddress $setBillingAddress,
        SetShippingAddress $setShippingAddress,
    ): RedirectResponse {
        $data = $request->validate([
            'billing' => ['required', 'array'],
            ...$this->prefixedRules('billing'),
            // Overrides the nullable default above: billing is where the
            // order confirmation email address comes from (see
            // CompleteCheckout), so it can't be optional there. Shipping's
            // own contact_email stays nullable - nothing sends mail to it.
            'billing.contact_email' => ['required', 'email', 'max:255'],
            'shipping' => ['required', 'array'],
            ...$this->prefixedRules('shipping'),
        ]);

        $setBillingAddress->handle($data['billing']);
        $setShippingAddress->handle($data['shipping']);

        return back();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function prefixedRules(string $prefix): array
    {
        $rules = [];

        foreach (self::ADDRESS_RULES as $field => $fieldRules) {
            $rules["{$prefix}.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    public function selectShipping(Request $request, SelectShippingOption $selectShippingOption): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        try {
            $selectShippingOption->handle($data['identifier']);
        } catch (CheckoutFailedException $exception) {
            return back()->withErrors(['identifier' => $exception->getMessage()]);
        }

        return back();
    }

    public function complete(Request $request, CompleteCheckout $completeCheckout): RedirectResponse
    {
        $data = $request->validate([
            'payment_intent' => ['required', 'string'],
        ]);

        return $this->finishPayment($data['payment_intent'], $completeCheckout);
    }

    /**
     * Return point for Stripe payment methods that actually redirect
     * (instead of resolving in JS via `redirect: 'if_required'`).
     */
    public function return(Request $request, CompleteCheckout $completeCheckout): RedirectResponse
    {
        $paymentIntentId = (string) $request->query('payment_intent');

        if ($paymentIntentId === '') {
            return redirect()->route('checkout.show')->withErrors(['payment' => 'Payment not found.']);
        }

        return $this->finishPayment($paymentIntentId, $completeCheckout);
    }

    public function confirmation(): Response|RedirectResponse
    {
        $order = session('checkout.last_order');

        if (! $order) {
            return redirect()->route('home');
        }

        return Inertia::render('storefront/checkout-confirmation', [
            'order' => $order,
            'meta' => (new PageMeta(title: 'Order confirmed', description: 'Your order is confirmed.', noindex: true))->toArray(),
        ]);
    }

    private function finishPayment(string $paymentIntentId, CompleteCheckout $completeCheckout): RedirectResponse
    {
        try {
            $order = $completeCheckout->handle($paymentIntentId);
        } catch (CheckoutFailedException $exception) {
            return redirect()->route('checkout.show')->withErrors(['payment' => $exception->getMessage()]);
        }

        session(['checkout.last_order' => $order->toArray()]);

        return redirect()->route('checkout.confirmation');
    }
}
