<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

use RuntimeException;

/**
 * Part of the `Port\CheckoutGateway::completePayment()` contract: the
 * payment or order creation failed for a business reason (card declined,
 * amount no longer matching the cart, incomplete address...). The
 * controller translates it into a redirect with an error message rather
 * than a 500.
 */
final class CheckoutFailedException extends RuntimeException {}
