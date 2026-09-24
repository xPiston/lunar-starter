<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Cart;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lunar\Base\Purchasable;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Validation\BaseValidator;

/**
 * Stock check against what the cart would end up holding, not against the
 * quantity being added.
 *
 * Replaces Lunar's own `CartLineStock`, which asks
 * `canBeFulfilledAtQuantity($quantity)` with the incoming quantity alone. Add
 * two of something with three in stock, then two more, and the cart holds
 * four: each request passed on its own. The customer only finds out at
 * checkout, or worse, after paying.
 *
 * This is not specific to bundles - a plain product variant behaves the same
 * way, verified against a real cart. Wired in config/lunar/cart.php in place
 * of Lunar's validator.
 */
final class CartLineTotalStock extends BaseValidator
{
    public function validate(): bool
    {
        /** @var ?Cart $cart */
        $cart = $this->parameters['cart'] ?? null;
        // Purchasable & Model: every purchasable is an Eloquent model at
        // runtime, but the contract alone knows nothing of getKey() or
        // getMorphClass().
        /** @var (Purchasable&Model)|null $purchasable */
        $purchasable = $this->parameters['purchasable'] ?? null;
        $quantity = (int) ($this->parameters['quantity'] ?? 0);
        $cartLineId = $this->parameters['cartLineId'] ?? null;

        // Updating a line: the incoming quantity replaces what is there, so
        // it is already the total. Lunar passes the line rather than the
        // purchasable in that case.
        if ($cartLineId && ! $purchasable && $cart) {
            /** @var (Purchasable&Model)|null $purchasable */
            $purchasable = $this->lines($cart)
                ->first(fn (CartLine $line): bool => $line->id == $cartLineId)
                ?->purchasable;
        } elseif ($cart && $purchasable) {
            // Adding: Lunar merges into the existing line for the same
            // purchasable, so what matters is the sum.
            $quantity += $this->quantityAlreadyInCart($cart, $purchasable);
        }

        if ($purchasable === null) {
            return $this->pass();
        }

        return $purchasable->canBeFulfilledAtQuantity($quantity)
            ? $this->pass()
            : $this->fail('cart', 'Item is not available at this quantity.');
    }

    /**
     * @param  Purchasable&Model  $purchasable
     */
    private function quantityAlreadyInCart(Cart $cart, Purchasable $purchasable): int
    {
        return (int) $this->lines($cart)
            ->filter(fn (CartLine $line): bool => $line->purchasable_type === $purchasable->getMorphClass()
                && (int) $line->purchasable_id === (int) $purchasable->getKey())
            ->sum('quantity');
    }

    /**
     * @return Collection<int, CartLine>
     */
    private function lines(Cart $cart): Collection
    {
        /** @var Collection<int, CartLine> $lines */
        $lines = $cart->lines;

        return $lines;
    }
}
