<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Domain\Checkout\ShippingOption;
use App\Infrastructure\Lunar\Support\MoneyMapper;
use Lunar\DataTypes\ShippingOption as LunarShippingOption;

final class ShippingOptionMapper
{
    public function __construct(private readonly MoneyMapper $money) {}

    public function toDomain(LunarShippingOption $option): ShippingOption
    {
        return new ShippingOption(
            identifier: $option->getIdentifier(),
            name: $option->getName(),
            description: $option->getDescription(),
            price: $this->money->fromLunarPrice($option->getPrice()),
        );
    }
}
