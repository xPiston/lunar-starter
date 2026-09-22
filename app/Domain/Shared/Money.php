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
    ) {}

    public function equals(Money $other): bool
    {
        return $this->minorAmount === $other->minorAmount
            && $this->currencyCode === $other->currencyCode;
    }

    /**
     * @return array{minor_amount: int, currency_code: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'minor_amount' => $this->minorAmount,
            'currency_code' => $this->currencyCode,
            'formatted' => $this->formatted,
        ];
    }
}
