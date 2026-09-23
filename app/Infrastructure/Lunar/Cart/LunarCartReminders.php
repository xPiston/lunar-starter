<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Cart;

use App\Domain\Cart\AbandonedCart;
use App\Domain\Cart\Port\CartReminders;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Cart as LunarCart;
use Lunar\Models\CartAddress;

/**
 * ADAPTER: finds abandoned Lunar carts and keeps the record of what was sent.
 *
 * The bookkeeping tables are read with the query builder rather than through
 * Eloquent models: two columns each, written and read only here, and nothing
 * else in the application has any use for them.
 */
final readonly class LunarCartReminders implements CartReminders
{
    public function __construct(private CartMapper $mapper) {}

    public function listDue(DateTimeImmutable $idleSince, DateTimeImmutable $notBefore, int $limit): array
    {
        $carts = LunarCart::query()
            // Still a cart, not a sale: `completed_at`/`order_id` are set when
            // checkout succeeds, and `merged_id` when Lunar folds a guest cart
            // into the account's own on login - reminding about a cart that
            // was merged would point at contents that moved elsewhere.
            ->whereNull('completed_at')
            ->whereNull('order_id')
            ->whereNull('merged_id')
            ->where('created_at', '>=', $notBefore)
            // Idle on both sides. Lunar's cart lines do not touch their cart's
            // timestamp, so `carts.updated_at` alone would call a cart someone
            // is actively filling "untouched since it was created".
            ->where('updated_at', '<=', $idleSince)
            ->whereHas('lines')
            ->whereDoesntHave('lines', fn ($query) => $query->where('updated_at', '>', $idleSince))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('cart_reminders')
                ->whereColumn('cart_reminders.cart_id', 'lunar_carts.id')
            )
            ->with(['lines', 'user', 'addresses'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $optedOut = DB::table('cart_reminder_opt_outs')->pluck('email')->all();

        return $carts
            ->map(fn (LunarCart $cart): ?AbandonedCart => $this->toDomain($cart, $optedOut))
            ->filter()
            ->values()
            ->all();
    }

    public function markReminded(int $cartId): void
    {
        // Upsert rather than insert: the unique index is the real guarantee
        // that nobody gets two emails, and a retried job must not blow up on
        // it.
        DB::table('cart_reminders')->upsert(
            ['cart_id' => $cartId, 'sent_at' => now()],
            ['cart_id'],
            ['sent_at'],
        );
    }

    public function optOut(string $email): void
    {
        DB::table('cart_reminder_opt_outs')->upsert(
            ['email' => mb_strtolower($email), 'created_at' => now()],
            ['email'],
            [],
        );
    }

    /**
     * @param  string[]  $optedOut
     */
    private function toDomain(LunarCart $cart, array $optedOut): ?AbandonedCart
    {
        $email = $this->emailFor($cart);

        // No address, no reminder. A guest who never reached checkout is
        // simply unreachable - there is nothing to fall back to.
        if ($email === null || in_array(mb_strtolower($email), $optedOut, true)) {
            return null;
        }

        // Totals are computed on demand, never stored: the email has to show
        // what the cart is worth today, not what it was when it was left.
        $calculated = $this->mapper->toDomain($cart->calculate());

        return new AbandonedCart(
            id: $cart->id,
            email: $email,
            firstName: $this->address($cart)?->first_name,
            lines: $calculated->lines,
            total: $calculated->total,
        );
    }

    /**
     * The account's address first: it is the one the person confirmed, while
     * a cart address is whatever was typed into checkout and abandoned
     * halfway - possibly mid-keystroke, hence the validity check.
     */
    private function emailFor(LunarCart $cart): ?string
    {
        // Typed locals because Lunar resolves its models dynamically
        // (`Model::modelClass()`), so these relations come back as a bare
        // Eloquent Model as far as static analysis is concerned.
        /** @var ?User $user */
        $user = $cart->user;

        $email = $user?->email;
        $email ??= $this->address($cart)?->contact_email;
        $email = is_string($email) ? trim($email) : null;

        return $email === null || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            ? null
            : $email;
    }

    /**
     * The shipping address if checkout got that far, otherwise the billing
     * one - they are filled in that order.
     */
    private function address(LunarCart $cart): ?CartAddress
    {
        /** @var ?CartAddress $shipping */
        $shipping = $cart->shippingAddress;
        /** @var ?CartAddress $billing */
        $billing = $cart->billingAddress;

        return $shipping ?? $billing;
    }
}
