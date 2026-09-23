<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Application\Cart\SendAbandonedCartReminders;
use App\Mail\AbandonedCartReminderMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\Country;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for abandoned cart reminders.
 *
 * Almost all of these are about restraint. Sending one email is easy; the
 * feature is only shippable if it provably never writes to someone who
 * bought, emptied their cart, already heard from us, unsubscribed, or never
 * gave us an address in the first place.
 */
final class AbandonedCartReminderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_a_cart_left_behind_earns_one_reminder(): void
    {
        $cart = $this->abandonedCart(email: 'shopper@example.com');

        $this->sendReminders();

        Mail::assertQueued(
            AbandonedCartReminderMail::class,
            fn (AbandonedCartReminderMail $mail): bool => $mail->hasTo('shopper@example.com')
                && $mail->cart->id === $cart->id
                && $mail->cart->itemCount() === 1
        );
    }

    /**
     * The whole feature runs on a schedule, so "once" has to survive being
     * run again an hour later on the same still-abandoned cart.
     */
    public function test_the_same_cart_is_never_reminded_twice(): void
    {
        $this->abandonedCart(email: 'shopper@example.com');

        $this->sendReminders();
        $this->sendReminders();

        Mail::assertQueuedCount(1);
    }

    public function test_a_cart_that_is_still_being_filled_is_left_alone(): void
    {
        // Idle cart row, but a line touched a minute ago: someone is shopping
        // right now. Lunar's lines don't touch their cart, so a check on the
        // cart's own timestamp alone would email this person mid-session.
        $cart = $this->abandonedCart(email: 'shopper@example.com');
        $cart->lines()->first()->forceFill(['updated_at' => now()->subMinute()])->save();

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    public function test_a_completed_cart_is_left_alone(): void
    {
        $cart = $this->abandonedCart(email: 'shopper@example.com');
        $this->amend($cart, ['completed_at' => now()]);

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    public function test_an_empty_cart_is_left_alone(): void
    {
        $cart = $this->abandonedCart(email: 'shopper@example.com');
        $cart->lines()->delete();

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    /**
     * A guest who never reached checkout has left no address anywhere. There
     * is nothing to fall back to, and inventing one is not an option.
     */
    public function test_a_cart_with_no_reachable_address_is_skipped(): void
    {
        $this->abandonedCart(email: null);

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    public function test_an_account_cart_is_reminded_at_the_account_address(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $cart = $this->abandonedCart(email: 'typed-at-checkout@example.com');
        $this->amend($cart, ['user_id' => $user->id]);

        $this->sendReminders();

        Mail::assertQueued(
            AbandonedCartReminderMail::class,
            fn (AbandonedCartReminderMail $mail): bool => $mail->hasTo('member@example.com')
        );
    }

    public function test_an_unsubscribed_address_is_never_written_to_again(): void
    {
        $this->abandonedCart(email: 'shopper@example.com');
        DB::table('cart_reminder_opt_outs')->insert(['email' => 'shopper@example.com', 'created_at' => now()]);

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    public function test_carts_older_than_the_cutoff_are_left_alone(): void
    {
        config(['abandoned_carts.ignore_older_than_days' => 7]);
        $cart = $this->abandonedCart(email: 'shopper@example.com');
        $this->amend($cart, ['created_at' => now()->subDays(30)]);

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    public function test_the_feature_can_be_switched_off_entirely(): void
    {
        config(['abandoned_carts.enabled' => false]);
        $this->abandonedCart(email: 'shopper@example.com');

        $this->sendReminders();

        Mail::assertNothingQueued();
    }

    /**
     * Marking happens before queueing: a mail driver that throws must not
     * leave the cart eligible for a second attempt on the next run. A
     * reminder that never arrives is a better failure than two that do.
     */
    public function test_a_failure_while_sending_does_not_re_arm_the_cart(): void
    {
        $cart = $this->abandonedCart(email: 'shopper@example.com');

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP is down'));

        try {
            $this->sendReminders();
        } catch (\RuntimeException) {
            // Expected - the point is what the database looks like afterwards.
        }

        $this->assertDatabaseHas('cart_reminders', ['cart_id' => $cart->id]);
    }

    /**
     * Changes a column without touching `updated_at`.
     *
     * Saving through Eloquent would stamp it with the current time, and the
     * cart would then be skipped for being freshly active rather than for the
     * reason each test is actually about - which is how two of these tests
     * passed for the wrong reason before this existed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function amend(Cart $cart, array $attributes): void
    {
        DB::table('lunar_carts')->where('id', $cart->id)->update($attributes);
    }

    private function sendReminders(): void
    {
        app(SendAbandonedCartReminders::class)->handle();
    }

    /**
     * A cart with one line, idle for two hours, optionally reachable.
     */
    private function abandonedCart(?string $email): Cart
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $cart = CartSession::current();
        $cart->add($product->variants->first(), 1);

        if ($email !== null) {
            $cart->setShippingAddress([
                'first_name' => 'Sam',
                'last_name' => 'Doe',
                'line_one' => '1 Test Street',
                'city' => 'Testville',
                'postcode' => 'TE5 7ER',
                'country_id' => Country::first()->id,
                'contact_email' => $email,
            ]);
        }

        $idle = now()->subHours(2);
        $cart->forceFill(['created_at' => $idle, 'updated_at' => $idle])->save();
        $cart->lines()->update(['updated_at' => $idle]);

        // The session still points at this cart; forget it so the test acts as
        // someone who has left, not as someone still on the site.
        CartSession::forget(delete: false);

        return $cart->refresh();
    }
}
