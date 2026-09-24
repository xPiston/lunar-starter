<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Cart;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLineException;
use App\Domain\Cart\CartRecovery;
use App\Domain\Cart\InvalidCouponException;
use App\Domain\Cart\Port\CartGateway;
use App\Models\ProductBundle;
use Illuminate\Support\Facades\Session;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Facades\CartSession;
use Lunar\Facades\Discounts;
use Lunar\Models\Cart as LunarCart;
use Lunar\Models\CartLine;
use Lunar\Models\ProductVariant;

/**
 * Outbound adapter: Lunar implementation of the CartGateway port.
 *
 * Only file (along with CartMapper) that imports `Lunar\Facades\CartSession`:
 * replacing Lunar with another cart engine means rewriting this class
 * (and CartMapper) so it keeps honoring CartGateway - nothing else in the
 * application needs to change.
 *
 * Assumes `config('lunar.cart_session.auto_create')` is `true` (see
 * config/lunar/cart_session.php): CartSession::current() therefore never
 * returns null.
 */
final class LunarCartGateway implements CartGateway
{
    public function __construct(private readonly CartMapper $mapper) {}

    public function current(): Cart
    {
        return $this->mapper->toDomain(CartSession::current());
    }

    /**
     * `CartSession::current()` is off-limits here: with `auto_create` on it
     * would persist a new cart row on every page view of the whole app.
     * Lunar's own "fetch without creating" path (`CartSessionManager::fetchOrCreate(create: false)`)
     * is protected, so this reads the same session key Lunar uses and sums
     * the lines directly - one aggregate query, and none at all for a
     * visitor with no cart yet.
     */
    public function currentItemCount(): int
    {
        $cartId = Session::get(CartSession::getSessionKey());

        if (! $cartId) {
            return 0;
        }

        return (int) CartLine::query()->where('cart_id', $cartId)->sum('quantity');
    }

    /**
     * `CartSession::use()` both puts the cart in the session and makes it the
     * one every later call in this request sees, which is what makes the
     * redirect to /cart show the restored contents rather than a new empty
     * cart.
     *
     * An emptied cart counts as unavailable: the link would "work" and land
     * the visitor on an empty page, which reads as a broken link.
     */
    public function restore(int $cartId, ?int $currentUserId): CartRecovery
    {
        $cart = LunarCart::query()
            ->whereNull('completed_at')
            ->whereNull('order_id')
            ->whereNull('merged_id')
            ->withCount('lines')
            ->find($cartId);

        if ($cart === null || $cart->lines_count === 0) {
            return CartRecovery::Unavailable;
        }

        if ($cart->user_id !== null && $cart->user_id !== $currentUserId) {
            return CartRecovery::RequiresLogin;
        }

        CartSession::use($cart);

        return CartRecovery::Restored;
    }

    public function addLine(int $productVariantId, int $quantity): Cart
    {
        $variant = ProductVariant::findOrFail($productVariantId);

        try {
            $cart = CartSession::current()->add($variant, $quantity);
        } catch (CartException $exception) {
            throw new CartLineException($exception->getMessage(), previous: $exception);
        }

        return $this->mapper->toDomain($cart);
    }

    /**
     * A bundle is one of this application's own models implementing Lunar's
     * `Purchasable`, so from here on it is the same call as any other line -
     * including the stock check, which the bundle answers from its scarcest
     * part.
     */
    public function addBundle(int $bundleId, int $quantity): Cart
    {
        $bundle = ProductBundle::query()
            ->active()
            ->with('items.variant')
            ->findOrFail($bundleId);

        try {
            $cart = CartSession::current()->add($bundle, $quantity);
        } catch (CartException $exception) {
            throw new CartLineException($exception->getMessage(), previous: $exception);
        }

        return $this->mapper->toDomain($cart);
    }

    public function updateLine(int $cartLineId, int $quantity): Cart
    {
        try {
            $cart = CartSession::current()->updateLine($cartLineId, $quantity);
        } catch (CartException $exception) {
            throw new CartLineException($exception->getMessage(), previous: $exception);
        }

        return $this->mapper->toDomain($cart);
    }

    public function removeLine(int $cartLineId): Cart
    {
        $cart = CartSession::current()->remove($cartLineId);

        return $this->mapper->toDomain($cart);
    }

    /**
     * `coupon_code` (a real column on `carts`) is all `Lunar\Pipelines\Cart\ApplyDiscounts`
     * needs to find and apply matching `Lunar\Models\Discount` rows on the
     * next `recalculate()` - nothing else to wire up. `Discounts::validateCoupon()`
     * only checks the code exists/isn't exhausted, not that it actually
     * applies to THIS cart's contents (product/collection-restricted
     * discounts), so that's checked separately via the resulting
     * `discountTotal` before accepting the code.
     */
    public function applyCoupon(string $code): Cart
    {
        $code = trim($code);

        if ($code === '' || ! Discounts::validateCoupon($code)) {
            throw new InvalidCouponException('This coupon code is invalid or has expired.');
        }

        $cart = CartSession::current();
        $cart->update(['coupon_code' => $code]);
        $cart = $cart->recalculate();

        if (! $cart->discountTotal || $cart->discountTotal->value <= 0) {
            $cart->update(['coupon_code' => null]);
            $cart->recalculate();

            throw new InvalidCouponException("This coupon code doesn't apply to the items in your cart.");
        }

        return $this->mapper->toDomain($cart);
    }

    public function removeCoupon(): Cart
    {
        $cart = CartSession::current();
        $cart->update(['coupon_code' => null]);
        $cart = $cart->recalculate();

        return $this->mapper->toDomain($cart);
    }
}
