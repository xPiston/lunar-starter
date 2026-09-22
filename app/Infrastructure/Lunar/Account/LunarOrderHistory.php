<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Account;

use App\Domain\Account\Port\OrderHistory;
use App\Domain\Checkout\Order;
use App\Infrastructure\Lunar\Checkout\OrderMapper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Lunar\Models\Order as LunarOrder;
use Lunar\Models\OrderAddress;

/**
 * Adapter sortant : implementation Lunar of the OrderHistory port.
 *
 * `Auth::user()` here, not a constructor-injected user id: `instanceof User`
 * is a real type guard (Auth::user() returns the generic Authenticatable
 * interface), and reading it lazily per-call means this class has no state
 * that could go stale across requests if the container ever reuses it.
 */
final class LunarOrderHistory implements OrderHistory
{
    public function __construct(
        private readonly OrderSummaryMapper $summaryMapper,
        private readonly OrderMapper $orderMapper,
    ) {}

    public function listForCurrentUser(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        return $user->orders()
            ->whereNotNull('placed_at')
            ->with('productLines')
            ->latest('placed_at')
            ->get()
            ->map($this->summaryMapper->toDomain(...))
            ->all();
    }

    public function findForCurrentUser(string $reference): ?Order
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $order = $user->orders()
            ->whereNotNull('placed_at')
            ->where('reference', $reference)
            ->first();

        return $order ? $this->orderMapper->toDomain($order) : null;
    }

    public function findByReferenceForGuest(string $reference, string $email): ?Order
    {
        $order = LunarOrder::query()
            ->whereNotNull('placed_at')
            ->where('reference', $reference)
            ->with('billingAddress')
            ->first();

        if (! $order) {
            return null;
        }

        /** @var ?OrderAddress $billingAddress */
        $billingAddress = $order->billingAddress;
        $ownerEmail = $billingAddress?->contact_email;

        if (! $ownerEmail || ! hash_equals(strtolower(trim($ownerEmail)), strtolower(trim($email)))) {
            return null;
        }

        return $this->orderMapper->toDomain($order);
    }
}
