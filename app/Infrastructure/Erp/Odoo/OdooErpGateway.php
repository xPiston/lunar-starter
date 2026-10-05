<?php

declare(strict_types=1);

namespace App\Infrastructure\Erp\Odoo;

use App\Domain\Checkout\Address;
use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpLine;
use App\Domain\Erp\ErpOrderLines;
use App\Domain\Erp\ErpReference;
use App\Domain\Erp\Port\ErpGateway;

/**
 * Pushes a paid order into Odoo as a customer (`res.partner`) and a sales
 * order (`sale.order`).
 *
 * Field names here were read off a live Odoo 18 through `fields_get`, not off
 * a blog post: `client_order_ref`, `product_uom_qty`, `price_unit`, the
 * `(0, 0, {...})` command tuples for one2many writes and `(6, 0, [])` to clear
 * a many2many. They are stable across recent majors, but that is where they
 * came from.
 */
final readonly class OdooErpGateway implements ErpGateway
{
    public function __construct(
        private OdooClient $client,
        private bool $confirmOrders,
    ) {}

    public function syncOrder(Order $order): ErpReference
    {
        $address = $order->billingAddress ?? $order->shippingAddress;

        // Asking Odoo rather than remembering locally: see the note on the
        // port. An order already there is returned as it stands - no second
        // order, and no attempt to reconcile lines a human may have edited.
        $existing = $this->client->searchFirst('sale.order', [['client_order_ref', '=', $order->reference]]);

        if ($existing !== null) {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = (array) $this->client->execute('sale.order', 'read', [[$existing], ['partner_id']]);
            $partner = $rows[0]['partner_id'] ?? null;

            return new ErpReference(
                driver: 'odoo',
                // Odoo returns a many2one as [id, display_name].
                customerId: is_array($partner) ? (string) ($partner[0] ?? '') : '',
                orderId: (string) $existing,
                alreadyPresent: true,
            );
        }

        $partnerId = $this->partnerFor($address);
        $orderId = $this->client->create('sale.order', [
            'partner_id' => $partnerId,
            // The shop's reference, and the key every later run matches on.
            'client_order_ref' => $order->reference,
            'order_line' => array_map($this->toLine(...), ErpOrderLines::for($order)),
        ]);

        if ($this->confirmOrders) {
            $this->client->execute('sale.order', 'action_confirm', [[$orderId]]);
        }

        return new ErpReference(
            driver: 'odoo',
            customerId: (string) $partnerId,
            orderId: (string) $orderId,
        );
    }

    /**
     * Finds the customer by email, or creates them.
     *
     * Email because it is the only thing a shop reliably knows about someone
     * who may have checked out as a guest. It is not a perfect key - a couple
     * sharing an address share a partner record - but the alternative, a new
     * partner per order, turns an ERP into a mailing list within a month.
     */
    private function partnerFor(?Address $address): int
    {
        $email = $address?->contactEmail;
        $name = $address === null ? '' : trim($address->firstName.' '.$address->lastName);

        if ($email !== null && $email !== '') {
            $found = $this->client->searchFirst('res.partner', [['email', '=', $email]]);

            if ($found !== null) {
                return $found;
            }
        }

        return $this->client->create('res.partner', array_filter([
            'name' => $name !== '' ? $name : ($email ?? 'Guest'),
            'email' => $email,
            'phone' => $address?->contactPhone,
            'street' => $address?->lineOne,
            'street2' => $address?->lineTwo,
            'zip' => $address?->postcode,
            'city' => $address?->city,
            'country_id' => $this->countryId($address?->countryIso),
            // Marks them as a customer rather than a bare contact, which is
            // what puts them in the customer lists staff actually use.
            'customer_rank' => 1,
            'company_type' => $this->isCompany($address) ? 'company' : 'person',
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function isCompany(?Address $address): bool
    {
        return $address !== null && ($address->companyName ?? '') !== '';
    }

    /**
     * Odoo wants its own row id for a country, so the ISO code is translated
     * here. An unknown or absent code leaves the field out rather than
     * failing: a partner without a country is still a usable partner.
     */
    private function countryId(?string $iso): ?int
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        return $this->client->searchFirst('res.country', [['code', '=', strtoupper($iso)]]);
    }

    /**
     * @return array{0: int, 1: int, 2: array<string, mixed>}
     */
    private function toLine(ErpLine $line): array
    {
        $values = [
            'name' => $line->label,
            'product_uom_qty' => $line->quantity,
            'price_unit' => (float) $line->unitPrice->toDecimal(),
            // Taxes cleared: the tax the shop charged arrives as its own
            // line instead. See the note in ErpOrderLines.
            'tax_id' => [[6, 0, []]],
        ];

        // Matching a line to a real product makes stock move; without a SKU it
        // stays a description line, which Odoo accepts and which still
        // invoices correctly.
        $productId = $this->productId($line->sku);

        if ($productId !== null) {
            $values['product_id'] = $productId;
        }

        // (0, 0, values) is Odoo's "create a new record in this one2many".
        return [0, 0, $values];
    }

    private function productId(?string $sku): ?int
    {
        if ($sku === null || $sku === '') {
            return null;
        }

        // `default_code` is what Odoo calls the internal reference - the field
        // labelled "Internal Reference" on the product form.
        return $this->client->searchFirst('product.product', [['default_code', '=', $sku]]);
    }
}
