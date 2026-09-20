<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use RuntimeException;

/**
 * Part of `Port\CartGateway::applyCoupon()`: the code doesn't exist, is
 * expired/exhausted, or doesn't apply to anything in the current cart. A
 * coupon code isn't sensitive the way an order reference is - there's no
 * enumeration concern in saying which of the two happened, so, unlike
 * `Application\Account\OrderNotFoundException`, this carries a specific
 * message rather than a generic one. The controller translates it into a
 * normal form error rather than a 500.
 */
final class InvalidCouponException extends RuntimeException {}
