<?php

declare(strict_types=1);

namespace App\Infrastructure\Erp\Dolibarr;

use App\Domain\Checkout\Address;
use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpLine;
use App\Domain\Erp\ErpOrderLines;
use App\Domain\Erp\ErpReference;
use App\Domain\Erp\Port\ErpGateway;

/**
 * Pushes a paid order into Dolibarr as a third party (`thirdparties`) and a
 * customer order (`orders`).
 *
 * Dolibarr keeps its French vocabulary in the API: a customer is a "third
 * party" with `client = 1`, an order line's unit price is `subprice`, and the
 * shop's own reference lives in `ref_client`. The names below were taken from
 * a live 24.0 instance rather than from the generated documentation, which
 * lists fields the endpoints quietly ignore.
 */
final readonly class DolibarrErpGateway implements ErpGateway
{
    public function __construct(
        private DolibarrClient $client,
        private bool $validateOrders,
    ) {}

    public function syncOrder(Order $order): ErpReference
    {
        $address = $order->billingAddress ?? $order->shippingAddress;

        $existing = $this->findOrder($order->reference);

        if ($existing !== null) {
            return new ErpReference(
                driver: 'dolibarr',
                customerId: (string) ($existing['socid'] ?? ''),
                orderId: (string) ($existing['id'] ?? ''),
                alreadyPresent: true,
            );
        }

        $thirdPartyId = $this->thirdPartyFor($address);

        $orderId = $this->client->post('orders', [
            'socid' => $thirdPartyId,
            'ref_client' => $order->reference,
            // Dolibarr wants a Unix timestamp; without one it dates the order
            // to whenever the queue happened to run it.
            'date' => now()->getTimestamp(),
            'lines' => array_map($this->toLine(...), ErpOrderLines::for($order)),
        ]);

        if ($this->validateOrders) {
            // Turns the draft into a real order and assigns the definitive
            // number, replacing the provisional `(PROVnn)`.
            $this->client->post("orders/{$orderId}/validate", []);
        }

        return new ErpReference(
            driver: 'dolibarr',
            customerId: (string) $thirdPartyId,
            orderId: (string) $orderId,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOrder(string $reference): ?array
    {
        $rows = $this->client->list('orders', [
            'sqlfilters' => "(t.ref_client:=:'".DolibarrClient::quote($reference)."')",
            'limit' => '1',
        ]);

        foreach ($rows as $row) {
            // Re-checked rather than trusted: `quote()` strips apostrophes to
            // keep the filter parseable, so a stripped value could in theory
            // match a different row. The comparison here is the real test.
            if (($row['ref_client'] ?? null) === $reference) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Finds the customer by email, or creates them. Same reasoning as the
     * Odoo adapter: email is the only identifier a guest checkout leaves
     * behind, and a new third party per order would be worse.
     */
    private function thirdPartyFor(?Address $address): int
    {
        $email = $address?->contactEmail;

        if ($email !== null && $email !== '') {
            $rows = $this->client->list('thirdparties', [
                'sqlfilters' => "(t.email:=:'".DolibarrClient::quote($email)."')",
                'limit' => '1',
            ]);

            foreach ($rows as $row) {
                if (($row['email'] ?? null) === $email) {
                    return (int) $row['id'];
                }
            }
        }

        $company = $address === null ? '' : (string) ($address->companyName ?? '');
        $person = $address === null ? '' : trim($address->firstName.' '.$address->lastName);
        // A company name wins over the contact's: it is what goes on the
        // invoice, and what staff look the customer up by.
        $name = $company !== '' ? $company : $person;

        return $this->client->post('thirdparties', array_filter([
            // Dolibarr refuses a third party with no name outright.
            'name' => $name !== '' ? $name : ($email ?? 'Guest'),
            // 1 = customer. Without it the record is a prospect, and orders
            // cannot be attached to it.
            'client' => 1,
            'email' => $email,
            'phone' => $address?->contactPhone,
            'address' => $this->street($address),
            'zip' => $address?->postcode,
            'town' => $address?->city,
            // Dolibarr resolves the ISO code to its own country id itself, so
            // unlike Odoo there is no lookup to do here.
            'country_code' => $address?->countryIso,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * Dolibarr keeps the street as ONE multi-line field, where the shop has
     * two, so they are joined rather than mapped across.
     */
    private function street(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        return trim($address->lineOne."\n".($address->lineTwo ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function toLine(ErpLine $line): array
    {
        return array_filter([
            'desc' => $line->label,
            // Matches an existing product by its reference when there is one;
            // Dolibarr falls back to a free line when there is not.
            'product_ref' => $line->sku,
            'qty' => $line->quantity,
            'subprice' => (float) $line->unitPrice->toDecimal(),
            // Zero VAT: the tax the shop charged arrives as its own line
            // instead. See the note in ErpOrderLines.
            'tva_tx' => 0,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
