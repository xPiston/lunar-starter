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
            'decimal_places' => 2,
        ], $money->toArray());
    }

    /**
     * `toDecimal` is what every system outside the shop reads - an ERP, an
     * accounting export, a carrier - so it is the one conversion that must not
     * be approximate.
     */
    public function test_it_renders_minor_units_as_a_decimal_string(): void
    {
        self::assertSame('19.99', (new Money(1999, 'EUR', ''))->toDecimal());
        self::assertSame('0.05', (new Money(5, 'EUR', ''))->toDecimal());
        self::assertSame('0.00', (new Money(0, 'EUR', ''))->toDecimal());
        self::assertSame('1000.00', (new Money(100000, 'EUR', ''))->toDecimal());
    }

    public function test_it_keeps_the_sign_of_a_negative_amount(): void
    {
        // Discount lines are sent to an ERP as negative amounts.
        self::assertSame('-15.00', (new Money(-1500, 'EUR', ''))->toDecimal());
        self::assertSame('-0.05', (new Money(-5, 'EUR', ''))->toDecimal());
    }

    public function test_it_follows_the_currency_rather_than_assuming_two_decimals(): void
    {
        // The yen has no minor unit at all: 1999 yen is 1999, not 19.99.
        self::assertSame('1999', (new Money(1999, 'JPY', '', 0))->toDecimal());
        // The Tunisian dinar has three.
        self::assertSame('1.999', (new Money(1999, 'TND', '', 3))->toDecimal());
    }

    public function test_a_string_because_a_float_cannot_hold_19_99(): void
    {
        // The reason toDecimal returns a string: round-tripping money through
        // a float is how totals end up a cent apart from what was charged.
        self::assertSame('0.29', (new Money(29, 'EUR', ''))->toDecimal());
        self::assertNotSame(0.29, (float) '0.1' + (float) '0.19');
    }
}
