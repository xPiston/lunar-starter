<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

/**
 * Extends PHPUnit\Framework\TestCase DIRECTLY (not Laravel's Tests\TestCase):
 * the domain doesn't boot any framework, exactly like the domain tests in
 * the poc-hexa-symfony/quarkus/go POCs.
 */
final class MoneyTest extends TestCase
{
    public function test_it_exposes_its_components(): void
    {
        $money = new Money(2499, 'USD', '$24.99');

        self::assertSame(2499, $money->minorAmount);
        self::assertSame('USD', $money->currencyCode);
        self::assertSame('$24.99', $money->formatted);
    }

    public function test_equality_is_based_on_amount_and_currency_not_formatting(): void
    {
        $a = new Money(2499, 'USD', '$24.99');
        $b = new Money(2499, 'USD', 'US$ 24.99');

        self::assertTrue($a->equals($b));
    }

    public function test_different_amounts_are_not_equal(): void
    {
        $a = new Money(2499, 'USD', '$24.99');
        $b = new Money(1999, 'USD', '$19.99');

        self::assertFalse($a->equals($b));
    }

    public function test_same_amount_in_different_currencies_are_not_equal(): void
    {
        $a = new Money(2499, 'USD', '$24.99');
        $b = new Money(2499, 'EUR', '24,99 €');

        self::assertFalse($a->equals($b));
    }

    public function test_to_array_matches_the_frontend_contract(): void
    {
        $money = new Money(2499, 'USD', '$24.99');

        self::assertSame([
            'minor_amount' => 2499,
            'currency_code' => 'USD',
            'formatted' => '$24.99',
        ], $money->toArray());
    }
}
