<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Immutable monetary value.
 *
 * Why a homegrown VO instead of letting `Lunar\DataTypes\Price` flow through
 * the domain/application/Inertia pages? Because this is exactly the
 * replacement boundary: if Lunar ever disappears, no class outside
 * `App\Infrastructure\Lunar` needs to change, not even for something as
 * mundane as a price.
 */
final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public string $currencyCode,
        public string $formatted,
        /**
         * How many of those minor units make a whole one.
         *
         * Two for most currencies, zero for the yen, three for the dinar.
         * Carried rather than assumed because the moment an amount leaves the
         * shop - to an ERP, an accounting export, a carrier - something has to
         * turn 1999 into 19.99, and guessing is how 1999 euros get invoiced.
         */
        public int $decimalPlaces = 2,
    ) {}

    /**
     * The amount as a decimal string: 1999 minor units becomes "19.99".
     *
     * A string, not a float. This is the value that goes out to other systems,
     * and a float cannot hold 19.99 exactly - round-tripping a few thousand
     * order lines through one is how totals end up a cent apart from what the
     * customer was charged.
     */
    public function toDecimal(): string
    {
        if ($this->decimalPlaces <= 0) {
            return (string) $this->minorAmount;
        }

        $negative = $this->minorAmount < 0;
        $digits = str_pad((string) abs($this->minorAmount), $this->decimalPlaces + 1, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '')
            .substr($digits, 0, -$this->decimalPlaces)
            .'.'
            .substr($digits, -$this->decimalPlaces);
    }

    public function equals(Money $other): bool
    {
        return $this->minorAmount === $other->minorAmount
            && $this->currencyCode === $other->currencyCode;
    }

    /**
     * @return array{minor_amount: int, currency_code: string, formatted: string, decimal_places: int}
     */
    public function toArray(): array
    {
        return [
            'minor_amount' => $this->minorAmount,
            'currency_code' => $this->currencyCode,
            'formatted' => $this->formatted,
            'decimal_places' => $this->decimalPlaces,
        ];
    }
}
