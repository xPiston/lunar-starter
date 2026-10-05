<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Erp;

use App\Domain\Checkout\Order;
use App\Domain\Checkout\OrderLine;
use App\Domain\Checkout\OrderStatus;
use App\Domain\Erp\ErpLine;
use App\Domain\Erp\ErpOrderLines;
use App\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

/**
 * The rule these tests exist for: whatever an ERP is told about an order, the
 * lines have to add up to what the customer was actually charged.
 *
 * It is written down because it was once broken. The first version of both
 * adapters pushed `$order->lines` and nothing else, so an order of 57.38
 * arrived in Odoo AND in Dolibarr as 52.48 - the shipping was simply missing,
 * because the domain keeps product lines in `lines` and everything else in its
 * own totals. Tax and discounts would have gone the same way.
 *
 * It was found by reading a real ERP rather than by a test, which is why the
 * test is here now.
 *
 * Plain PHPUnit, like the rest of tests/Unit/Domain: this code boots no
 * framework, so neither does its test.
 */
final class ErpOrderLinesTest extends TestCase
{
    public function test_a_plain_order_adds_up_to_its_total(): void
    {
        $order = $this->order(subTotal: 5248);

        self::assertSame($order->total->minorAmount, $this->sum(ErpOrderLines::for($order)));
    }

    public function test_shipping_is_carried_as_a_line_so_the_total_still_matches(): void
    {
        // The exact order that was wrong: 52.48 of goods, 4.90 of delivery.
        $order = $this->order(subTotal: 5248, shipping: 490);

        $lines = ErpOrderLines::for($order);

        self::assertSame(5738, $order->total->minorAmount);
        self::assertSame(5738, $this->sum($lines));
        self::assertContains(ErpOrderLines::SHIPPING_LABEL, $this->labels($lines));
    }

    public function test_shipping_a_discount_and_tax_together_still_add_up(): void
    {
        $order = $this->order(subTotal: 10000, shipping: 599, discount: 1500, tax: 1720);

        self::assertSame($order->total->minorAmount, $this->sum(ErpOrderLines::for($order)));
    }

    public function test_a_discount_subtracts(): void
    {
        $lines = ErpOrderLines::for($this->order(subTotal: 10000, discount: 1500));

        $discount = $this->lineLabelled($lines, ErpOrderLines::DISCOUNT_LABEL);

        self::assertNotNull($discount);
        self::assertSame(-1500, $discount->totalMinorAmount());
    }

    public function test_tax_is_carried_as_its_own_line(): void
    {
        $lines = ErpOrderLines::for($this->order(subTotal: 10000, tax: 2000));

        $tax = $this->lineLabelled($lines, ErpOrderLines::TAX_LABEL);

        self::assertNotNull($tax);
        self::assertSame(2000, $tax->totalMinorAmount());
    }

    public function test_nothing_is_sent_for_amounts_that_are_zero(): void
    {
        // Free delivery, no discount, no tax: an ERP should not receive three
        // lines worth nothing.
        $labels = $this->labels(ErpOrderLines::for($this->order(subTotal: 2500)));

        self::assertNotContains(ErpOrderLines::SHIPPING_LABEL, $labels);
        self::assertNotContains(ErpOrderLines::DISCOUNT_LABEL, $labels);
        self::assertNotContains(ErpOrderLines::TAX_LABEL, $labels);
    }

    public function test_only_product_lines_carry_a_sku(): void
    {
        // The SKU is how an ERP finds the product in its own catalogue, so
        // shipping must not claim one.
        $lines = ErpOrderLines::for($this->order(subTotal: 2500, shipping: 500));

        self::assertSame('SKU-1', $lines[0]->sku);
        self::assertSame(ErpOrderLines::SHIPPING_LABEL, $lines[1]->label);
        self::assertNull($lines[1]->sku);
    }

    public function test_quantities_are_multiplied_not_assumed_to_be_one(): void
    {
        $order = $this->order(
            subTotal: 3998,
            lines: [new OrderLine(1, 'A thing', null, 2, $this->money(1999), $this->money(3998), 'SKU-1')],
        );

        self::assertSame(3998, $this->sum(ErpOrderLines::for($order)));
    }

    private function money(int $minor): Money
    {
        return new Money($minor, 'EUR', number_format($minor / 100, 2).' EUR');
    }

    /**
     * @param  OrderLine[]|null  $lines
     */
    private function order(
        int $subTotal,
        int $shipping = 0,
        int $discount = 0,
        int $tax = 0,
        ?array $lines = null,
    ): Order {
        return new Order(
            id: 1,
            reference: 'REF-1',
            placed: true,
            status: new OrderStatus('payment-received', 'Payment received'),
            lines: $lines ?? [
                new OrderLine(1, 'A thing', null, 1, $this->money($subTotal), $this->money($subTotal), 'SKU-1'),
            ],
            shippingAddress: null,
            billingAddress: null,
            subTotal: $this->money($subTotal),
            shippingTotal: $this->money($shipping),
            discountTotal: $this->money($discount),
            taxTotal: $this->money($tax),
            total: $this->money($subTotal + $shipping + $tax - $discount),
        );
    }

    /**
     * @param  ErpLine[]  $lines
     */
    private function sum(array $lines): int
    {
        return array_sum(array_map(
            static fn (ErpLine $line): int => $line->totalMinorAmount(),
            $lines,
        ));
    }

    /**
     * @param  ErpLine[]  $lines
     * @return string[]
     */
    private function labels(array $lines): array
    {
        return array_map(static fn (ErpLine $line): string => $line->label, $lines);
    }

    /**
     * @param  ErpLine[]  $lines
     */
    private function lineLabelled(array $lines, string $label): ?ErpLine
    {
        foreach ($lines as $line) {
            if ($line->label === $label) {
                return $line;
            }
        }

        return null;
    }
}
