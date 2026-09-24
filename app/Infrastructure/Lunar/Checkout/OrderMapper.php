<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Checkout\Order;
use App\Domain\Checkout\OrderLine;
use App\Domain\Checkout\OrderStatus;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\Models\Order as LunarOrder;
use Lunar\Models\OrderLine as LunarOrderLine;
use Lunar\Models\ProductVariant as LunarProductVariant;

final class OrderMapper
{
    public function __construct(
        private readonly MoneyMapper $money,
        private readonly AddressMapper $addresses,
    ) {}

    public function toDomain(LunarOrder $order): Order
    {
        return new Order(
            id: $order->id,
            reference: (string) $order->reference,
            placed: $order->placed_at !== null,
            // `status` is the handle a shop configures in config/lunar/orders.php;
            // `status_label` is what Lunar resolves from it for display.
            status: new OrderStatus((string) $order->status, (string) $order->status_label),
            lines: $order->productLines
                ->map($this->toLine(...))
                ->all(),
            shippingAddress: $order->shippingAddress ? $this->addresses->toDomain($order->shippingAddress) : null,
            billingAddress: $order->billingAddress ? $this->addresses->toDomain($order->billingAddress) : null,
            // Unlike Cart (transient properties recalculated on the fly),
            // Order persists its totals in the database in snake_case, cast
            // to Lunar\DataTypes\Price via Lunar\Base\Casts\Price.
            subTotal: $this->money->fromLunarPrice($order->sub_total),
            shippingTotal: $this->money->fromLunarPrice($order->shipping_total),
            discountTotal: $this->money->fromLunarPrice($order->discount_total),
            taxTotal: $this->money->fromLunarPrice($order->tax_total),
            total: $this->money->fromLunarPrice($order->total),
        );
    }

    private function toLine(LunarOrderLine $line): OrderLine
    {
        /** @var ?LunarProductVariant $variant */
        $variant = $line->purchasable;

        return new OrderLine(
            id: $line->id,
            name: $line->description,
            thumbnailUrl: $this->nullIfEmpty($variant?->product?->getThumbnailImage() ?? ''),
            quantity: $line->quantity,
            unitPrice: $this->money->fromLunarPrice($line->unit_price),
            lineTotal: $this->money->fromLunarPrice($line->total),
        );
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
