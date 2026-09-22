<?php

declare(strict_types=1);

namespace App\Domain\Cart\Port;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLineException;
use App\Domain\Cart\InvalidCouponException;

/**
 * OUTBOUND port of the Cart context.
 *
 * Only `App\Infrastructure\Lunar\Cart\LunarCartGateway` is allowed to import
 * Lunar code to implement this contract (`Lunar\Facades\CartSession`,
 * `Lunar\Models\ProductVariant`...). The rest of the application (use cases,
 * Storefront controllers, Inertia pages) only knows this interface.
 */
interface CartGateway
{
    public function current(): Cart;

    /**
     * Total quantity across the current cart's lines, for the header badge.
     *
     * Deliberately NOT `current()->lines` + a sum: this one must be free of
     * side effects (never creates a cart, never runs the calculation
     * pipeline) because it's resolved on every Inertia response, including
     * dashboard and auth pages that have nothing to do with the shop. A
     * visitor who never added anything costs zero queries here.
     */
    public function currentItemCount(): int;

    /**
     * @throws CartLineException Not enough stock, quantity
     *                           below the product's minimum/increment, or the product is no longer purchasable.
     */
    public function addLine(int $productVariantId, int $quantity): Cart;

    /**
     * @throws CartLineException Same reasons as addLine().
     */
    public function updateLine(int $cartLineId, int $quantity): Cart;

    public function removeLine(int $cartLineId): Cart;

    /**
     * @throws InvalidCouponException The code doesn't exist, is expired/exhausted,
     *                                or doesn't apply to anything currently in the cart.
     */
    public function applyCoupon(string $code): Cart;

    public function removeCoupon(): Cart;
}
