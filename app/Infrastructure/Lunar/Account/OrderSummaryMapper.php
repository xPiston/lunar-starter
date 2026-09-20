<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Account;

use App\Domain\Account\OrderSummary;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\Models\Order as LunarOrder;

final class OrderSummaryMapper
{
    public function __construct(private readonly MoneyMapper $money) {}

    /**
     * Expects `productLines` already eager-loaded by the caller (a list of
     * these is rendered per-row, so N+1 here would mean N+1 queries for the
     * whole order history page).
     */
    public function toDomain(LunarOrder $order): OrderSummary
    {
        return new OrderSummary(
            reference: (string) $order->reference,
            placedAt: $order->placed_at?->format('d M Y') ?? '',
            status: $order->status_label,
            itemCount: (int) $order->productLines->sum('quantity'),
            total: $this->money->fromLunarPrice($order->total),
        );
    }
}
