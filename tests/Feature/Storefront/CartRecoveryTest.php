<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the links an abandoned cart reminder carries.
 *
 * These URLs travel through email, which means through other people's
 * inboxes, mail scanners and forwards. The signature is what stands between
 * "restore my cart" and "restore any cart whose id I can guess", so the tests
 * that matter most here are the ones that try to skip it.
 */
final class CartRecoveryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_signed_link_puts_the_cart_back(): void
    {
        $cart = $this->abandonedCart();

        $this->get($this->recoveryUrl($cart))
            ->assertRedirect(route('cart.show'));

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cart.lines', 1)
                ->where('cart.id', $cart->id)
            );
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $cart = $this->abandonedCart();

        $this->get(route('cart.recover', ['cartId' => $cart->id]))
            ->assertForbidden();
    }

    /**
     * Changing the id in a valid link is the obvious attack: one signed link
     * would otherwise be a key to every cart in the shop.
     */
    public function test_a_link_edited_to_point_at_another_cart_is_refused(): void
    {
        $mine = $this->abandonedCart();
        $someoneElses = $this->abandonedCart();

        $tampered = str_replace(
            '/cart/recover/'.$mine->id,
            '/cart/recover/'.$someoneElses->id,
            $this->recoveryUrl($mine),
        );

        $this->get($tampered)->assertForbidden();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $cart = $this->abandonedCart();
        $url = URL::temporarySignedRoute('cart.recover', now()->addDay(), ['cartId' => $cart->id]);

        $this->travel(2)->days();

        $this->get($url)->assertForbidden();
    }

    /**
     * A cart attached to an account carries the address that account typed in.
     * A forwarded email must not hand that to whoever opens it.
     */
    public function test_an_account_cart_is_not_handed_to_a_stranger(): void
    {
        $owner = User::factory()->create();
        $cart = $this->abandonedCart();
        DB::table('lunar_carts')->where('id', $cart->id)->update(['user_id' => $owner->id]);

        $this->get($this->recoveryUrl($cart))
            ->assertRedirect(route('login'));

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->has('cart.lines', 0));
    }

    public function test_the_owner_signing_in_gets_their_cart_back(): void
    {
        $owner = User::factory()->create();
        $cart = $this->abandonedCart();
        DB::table('lunar_carts')->where('id', $cart->id)->update(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get($this->recoveryUrl($cart))
            ->assertRedirect(route('cart.show'));

        $this->actingAs($owner)
            ->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->has('cart.lines', 1));
    }

    public function test_a_cart_that_was_ordered_in_the_meantime_sends_them_home(): void
    {
        $cart = $this->abandonedCart();
        DB::table('lunar_carts')->where('id', $cart->id)->update(['completed_at' => now()]);

        $this->get($this->recoveryUrl($cart))
            ->assertRedirect(route('home'));
    }

    public function test_the_unsubscribe_link_records_the_opt_out(): void
    {
        $url = URL::temporarySignedRoute('cart.reminders.unsubscribe', now()->addWeek(), ['email' => 'Shopper@Example.com']);

        $this->get($url)->assertRedirect(route('home'));

        // Stored lowercased: the same person writing their address with
        // different capitalisation is still the same person.
        $this->assertDatabaseHas('cart_reminder_opt_outs', ['email' => 'shopper@example.com']);
    }

    public function test_an_unsigned_unsubscribe_link_is_refused(): void
    {
        $this->get(route('cart.reminders.unsubscribe', ['email' => 'victim@example.com']))
            ->assertForbidden();

        $this->assertDatabaseCount('cart_reminder_opt_outs', 0);
    }

    private function recoveryUrl(Cart $cart): string
    {
        return URL::temporarySignedRoute('cart.recover', now()->addWeek(), ['cartId' => $cart->id]);
    }

    /**
     * A cart with one line, left behind: the session is cleared afterwards so
     * the test browses as someone arriving fresh from an email.
     */
    private function abandonedCart(): Cart
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $cart = CartSession::current();
        $cart->add($product->variants->first(), 1);

        CartSession::forget(delete: false);

        return $cart->refresh();
    }
}
