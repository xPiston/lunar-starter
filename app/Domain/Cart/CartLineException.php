<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use RuntimeException;

/**
 * Part of `Port\CartGateway::addLine()`/`updateLine()`: the mutation was
 * rejected for a business reason (not enough stock, quantity below the
 * product's minimum/increment, product no longer purchasable...). The
 * controller translates it into a redirect with a form error rather than a
 * 500 - see `App\Infrastructure\Lunar\Cart\LunarCartGateway`, the only place
 * that throws it, wrapping Lunar's own `CartException`.
 */
final class CartLineException extends RuntimeException {}
