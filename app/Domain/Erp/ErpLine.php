<?php

declare(strict_types=1);

namespace App\Domain\Erp;

use App\Domain\Shared\Money;

/**
 * One line of an order as an ERP will record it.
 *
 * Deliberately NOT `Checkout\OrderLine`. An order's lines are the things the
 * customer chose; what an ERP has to be told is everything that made up the
 * amount they were charged - the products, but also the shipping, the discount
 * and the tax, none of which are products and all of which have to appear
 * somewhere or the ERP's total will not match the payment.
 */
final readonly class ErpLine
{
    public function __construct(
        public string $label,
        public int $quantity,
        public Money $unitPrice,
        /** Set only on real product lines; the ERP matches its catalogue on it. */
        public ?string $sku = null,
    ) {}

    /** The amount this line contributes, in minor units. */
    public function totalMinorAmount(): int
    {
        return $this->quantity * $this->unitPrice->minorAmount;
    }
}
