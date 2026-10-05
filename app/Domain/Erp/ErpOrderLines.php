<?php

declare(strict_types=1);

namespace App\Domain\Erp;

use App\Domain\Checkout\Order;
use App\Domain\Shared\Money;

/**
 * Turns an order into the lines an ERP has to be told about.
 *
 * This exists because of a bug worth remembering. The first version of both
 * adapters pushed `$order->lines` and nothing else, and both ERPs dutifully
 * recorded an order of 52.48 for a customer who had paid 57.38 - the shipping
 * was simply not there, because the shop's domain model keeps PRODUCT lines in
 * `lines` and everything else in its own total. Tax and discounts would have
 * gone the same way.
 *
 * So the rule lives here, once, rather than in each adapter where the two
 * could drift: whatever an ERP is told, the lines must add up to what the
 * customer was actually charged. `AddsUpToOrderTotal` in the tests is that
 * sentence as an assertion.
 */
final readonly class ErpOrderLines
{
    public const string SHIPPING_LABEL = 'Shipping';

    public const string DISCOUNT_LABEL = 'Discount';

    public const string TAX_LABEL = 'Tax';

    /**
     * @return ErpLine[]
     */
    public static function for(Order $order): array
    {
        $lines = array_map(
            static fn ($line): ErpLine => new ErpLine(
                label: $line->name,
                quantity: $line->quantity,
                unitPrice: $line->unitPrice,
                sku: $line->sku,
            ),
            $order->lines,
        );

        // Each of these is a single line of quantity one, priced at the total
        // the order already worked out. Nothing is recomputed here - the shop
        // decided these amounts when it took the money, and this is a
        // transcription, not a second opinion.
        if ($order->shippingTotal->minorAmount !== 0) {
            $lines[] = new ErpLine(self::SHIPPING_LABEL, 1, $order->shippingTotal);
        }

        if ($order->discountTotal->minorAmount !== 0) {
            // Negative: a discount is money the customer did not pay, and an
            // ERP line that subtracts is how every one of them expresses that.
            $lines[] = new ErpLine(self::DISCOUNT_LABEL, 1, self::negate($order->discountTotal));
        }

        /*
         * Tax as a line, not as the ERP's own tax code.
         *
         * The adapters push every line tax-free on purpose: the customer has
         * been charged, and letting an ERP re-apply its own rates would make
         * it disagree with the payment processor by a few cents on every
         * order - the kind of gap that only surfaces weeks later, in
         * accounting. Carrying the tax the shop actually charged as its own
         * line keeps the totals equal.
         *
         * The trade-off, stated plainly: the ERP sees this as an ordinary
         * line, not as tax, so it will not appear in its VAT reports. A shop
         * that needs that should map its rates onto the ERP's tax codes in the
         * adapters instead, and accept that the two systems can then differ.
         */
        if ($order->taxTotal->minorAmount !== 0) {
            $lines[] = new ErpLine(self::TAX_LABEL, 1, $order->taxTotal);
        }

        return $lines;
    }

    private static function negate(Money $money): Money
    {
        return new Money(
            minorAmount: -$money->minorAmount,
            currencyCode: $money->currencyCode,
            formatted: '-'.$money->formatted,
            decimalPlaces: $money->decimalPlaces,
        );
    }
}
