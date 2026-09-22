<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Account\LookupGuestOrder;
use App\Application\Account\OrderNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a guest (no account) find an order by reference + the email given at
 * checkout - the same "order number + email" pattern used by most stores,
 * for the customer who never created an account.
 *
 * Same security pattern as `CheckoutController::confirmation()`: the result
 * page reads only from the session, never from the URL, so there is no
 * `GET /orders/lookup/{reference}` to scrape - reaching the result page at
 * all already proves the reference+email pair was verified server-side.
 */
final class GuestOrderLookupController extends Controller
{
    private const SESSION_KEY = 'guest_order_lookup.result';

    public function show(): Response
    {
        return Inertia::render('storefront/orders/lookup');
    }

    public function find(Request $request, LookupGuestOrder $lookupGuestOrder): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        try {
            $order = $lookupGuestOrder->handle($data['reference'], $data['email']);
        } catch (OrderNotFoundException) {
            // Same message whether the reference doesn't exist or the email
            // doesn't match it - see OrderHistory::findByReferenceForGuest().
            return back()->withErrors(['lookup' => 'No order found matching that reference and email.']);
        }

        session([self::SESSION_KEY => $order->toArray()]);

        return redirect()->route('orders.lookup.result');
    }

    public function result(): Response|RedirectResponse
    {
        $order = session(self::SESSION_KEY);

        if (! $order) {
            return redirect()->route('orders.lookup');
        }

        return Inertia::render('storefront/orders/lookup-result', [
            'order' => $order,
        ]);
    }
}
