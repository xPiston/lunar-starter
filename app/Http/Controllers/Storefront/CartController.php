<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Cart\AddProductToCart;
use App\Application\Cart\ApplyCoupon;
use App\Application\Cart\RemoveCartLine;
use App\Application\Cart\RemoveCoupon;
use App\Application\Cart\UpdateCartLineQuantity;
use App\Application\Cart\ViewCart;
use App\Domain\Cart\CartLineException;
use App\Domain\Cart\InvalidCouponException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CartController extends Controller
{
    public function show(ViewCart $viewCart): Response
    {
        return Inertia::render('storefront/cart', [
            'cart' => $viewCart->handle()->toArray(),
        ]);
    }

    public function store(Request $request, AddProductToCart $addProductToCart): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        try {
            $addProductToCart->handle(
                productVariantId: (int) $data['product_variant_id'],
                quantity: (int) ($data['quantity'] ?? 1),
            );
        } catch (CartLineException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return back()->with('success', 'Product added to cart.');
    }

    public function update(Request $request, int $line, UpdateCartLineQuantity $updateCartLineQuantity): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $updateCartLineQuantity->handle($line, (int) $data['quantity']);
        } catch (CartLineException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(int $line, RemoveCartLine $removeCartLine): RedirectResponse
    {
        $removeCartLine->handle($line);

        return back();
    }

    public function applyCoupon(Request $request, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        try {
            $applyCoupon->handle($data['code']);
        } catch (InvalidCouponException $exception) {
            return back()->withErrors(['coupon' => $exception->getMessage()]);
        }

        return back();
    }

    public function removeCoupon(RemoveCoupon $removeCoupon): RedirectResponse
    {
        $removeCoupon->handle();

        return back();
    }
}
