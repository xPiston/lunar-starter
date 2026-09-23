<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Cart\OptOutOfCartReminders;
use App\Application\Cart\RecoverCart;
use App\Domain\Cart\CartRecovery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two links an abandoned cart reminder carries.
 *
 * Both routes are behind the `signed` middleware: the cart id and the email
 * address travel in the URL, so without a signature anyone could restore
 * someone else's cart by counting, or unsubscribe an address they don't own.
 * Laravel answers 403 on a tampered or expired signature before any of this
 * runs.
 */
final class CartRecoveryController extends Controller
{
    public function recover(RecoverCart $recoverCart, Request $request, int $cartId): RedirectResponse
    {
        $result = $recoverCart->handle($cartId, $request->user()?->id);

        return match ($result) {
            CartRecovery::Restored => redirect()
                ->route('cart.show')
                ->with('status', 'Welcome back - your cart is just as you left it.'),

            // Sending them to the login page with the link as the intended
            // URL means signing in finishes the job: they land back here and
            // the cart is restored, rather than having to find the email
            // again.
            CartRecovery::RequiresLogin => redirect()
                ->guest(route('login'))
                ->with('status', 'Sign in to pick your cart back up.'),

            CartRecovery::Unavailable => redirect()
                ->route('home')
                ->with('status', "That cart isn't available any more - it may have been ordered already."),
        };
    }

    /**
     * The address travels as a query parameter rather than a path segment:
     * it is covered by the signature either way, and this keeps an `@` and
     * dots out of the URL path where they would need escaping.
     */
    public function unsubscribe(OptOutOfCartReminders $optOut, Request $request): RedirectResponse
    {
        $email = trim((string) $request->query('email'));

        if ($email === '') {
            abort(404);
        }

        $optOut->handle($email);

        return redirect()
            ->route('home')
            ->with('status', "You won't get cart reminders from us again.");
    }
}
