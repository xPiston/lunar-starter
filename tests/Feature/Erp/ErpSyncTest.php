<?php

declare(strict_types=1);

namespace Tests\Feature\Erp;

use App\Domain\Checkout\Address;
use App\Domain\Checkout\Order;
use App\Domain\Checkout\OrderLine;
use App\Domain\Checkout\OrderStatus;
use App\Domain\Erp\ErpOrderLines;
use App\Domain\Erp\Port\ErpGateway;
use App\Domain\Shared\Money;
use App\Infrastructure\Erp\NullErpGateway;
use App\Jobs\SyncOrderToErpJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CONTRACT tests for the ERP integration.
 *
 * The adapters were built against a real Odoo 18 and a real Dolibarr 24 (see
 * docker-compose.erp.yml), which is the only way to find out what those APIs
 * actually accept. These tests pin what was learned there - the field names,
 * the idempotency key, the shape of the payload - so CI can defend it without
 * running two ERPs.
 *
 * What they deliberately do NOT do is assert that an ERP likes the payload.
 * Only an ERP can answer that, and a mock agreeing with itself would be worse
 * than no test at all.
 */
final class ErpSyncTest extends TestCase
{
    public function test_a_shop_without_an_erp_gets_a_gateway_that_does_nothing(): void
    {
        // The default, and the whole point of the feature being optional: no
        // configuration, no service to run, no conditional in the callers.
        config()->set('erp.driver', 'none');

        self::assertInstanceOf(NullErpGateway::class, $this->gateway());
    }

    public function test_the_null_gateway_reports_the_shops_own_reference(): void
    {
        config()->set('erp.driver', 'none');

        $reference = $this->gateway()->syncOrder($this->order());

        self::assertSame('none', $reference->driver);
        self::assertSame('REF-1', $reference->orderId);
    }

    public function test_a_misspelled_driver_fails_loudly_rather_than_silently_doing_nothing(): void
    {
        // ERP_DRIVER=oddo must not look like a shop that chose to have no ERP:
        // that is the kind of failure nobody notices until an accountant asks
        // where three weeks of orders went.
        config()->set('erp.driver', 'oddo');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown ERP driver [oddo]');

        $this->gateway();
    }

    public function test_completing_a_checkout_queues_the_sync(): void
    {
        // Queued, not inline: the customer has paid, and an ERP mid-upgrade
        // must not turn that into a 500 on the thank-you page.
        self::assertTrue(
            is_subclass_of(SyncOrderToErpJob::class, ShouldQueue::class),
        );
    }

    public function test_odoo_receives_the_order_with_the_shops_reference_as_its_key(): void
    {
        config()->set('erp.driver', 'odoo');
        config()->set('erp.odoo.url', 'http://odoo.test');
        config()->set('erp.odoo.database', 'shop');
        config()->set('erp.odoo.username', 'admin');
        config()->set('erp.odoo.api_key', 'key');

        Http::fake(['odoo.test/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'result' => 2])      // authenticate -> uid
            ->push(['jsonrpc' => '2.0', 'result' => []])     // sale.order search -> none yet
            ->push(['jsonrpc' => '2.0', 'result' => []])     // res.partner search -> unknown
            ->push(['jsonrpc' => '2.0', 'result' => [75]])   // res.country search -> France
            ->push(['jsonrpc' => '2.0', 'result' => 7])      // res.partner create
            // One lookup per line that carries a SKU - the tote bag has none,
            // so it is not looked up and stays a description line.
            ->push(['jsonrpc' => '2.0', 'result' => []])     // product.product search -> no match
            ->push(['jsonrpc' => '2.0', 'result' => 42]),    // sale.order create
        ]);

        $reference = $this->gateway()->syncOrder($this->order());

        self::assertSame('odoo', $reference->driver);
        self::assertSame('42', $reference->orderId);
        self::assertSame('7', $reference->customerId);
        self::assertFalse($reference->alreadyPresent);

        $create = $this->odooCall('sale.order', 'create');

        self::assertSame('REF-1', $create[0]['client_order_ref']);
        self::assertSame(7, $create[0]['partner_id']);

        // Shipping travels as a line, or the ERP's total would be 4.90 short
        // of what the customer paid - the bug that put ErpOrderLines there.
        $labels = array_map(static fn (array $line): string => $line[2]['name'], $create[0]['order_line']);
        self::assertContains(ErpOrderLines::SHIPPING_LABEL, $labels);

        $total = array_sum(array_map(
            static fn (array $line): float => $line[2]['product_uom_qty'] * $line[2]['price_unit'],
            $create[0]['order_line'],
        ));
        self::assertEqualsWithDelta(57.38, $total, 0.001);

        // Taxes cleared on every line: the shop already charged what it
        // charged, and Odoo re-applying its own rates would disagree with the
        // payment processor.
        foreach ($create[0]['order_line'] as $line) {
            self::assertSame([[6, 0, []]], $line[2]['tax_id']);
        }
    }

    public function test_odoo_does_not_create_a_second_order_when_the_reference_is_already_there(): void
    {
        config()->set('erp.driver', 'odoo');
        config()->set('erp.odoo.url', 'http://odoo.test');
        config()->set('erp.odoo.database', 'shop');
        config()->set('erp.odoo.username', 'admin');
        config()->set('erp.odoo.api_key', 'key');

        Http::fake(['odoo.test/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'result' => 2])        // authenticate
            ->push(['jsonrpc' => '2.0', 'result' => [42]])     // the order is already there
            ->push(['jsonrpc' => '2.0', 'result' => [['id' => 42, 'partner_id' => [7, 'Ada']]]]),
        ]);

        $reference = $this->gateway()->syncOrder($this->order());

        self::assertTrue($reference->alreadyPresent);
        self::assertSame('42', $reference->orderId);
        self::assertSame('7', $reference->customerId);

        // The retry must not have written anything.
        Http::assertNotSent(fn (Request $request): bool => str_contains(
            (string) $request->body(), '"method":"create"',
        ));
    }

    public function test_dolibarr_receives_the_order_with_the_shops_reference_as_its_key(): void
    {
        config()->set('erp.driver', 'dolibarr');
        config()->set('erp.dolibarr.url', 'http://dolibarr.test');
        config()->set('erp.dolibarr.api_key', 'key');

        Http::fake([
            // Dolibarr answers 404, not an empty list, when a filter matches
            // nothing - which the client folds into "none".
            'dolibarr.test/api/index.php/orders?*' => Http::response(['error' => 'not found'], 404),
            'dolibarr.test/api/index.php/thirdparties?*' => Http::response(['error' => 'not found'], 404),
            'dolibarr.test/api/index.php/thirdparties' => Http::response('5'),
            'dolibarr.test/api/index.php/orders' => Http::response('42'),
        ]);

        $reference = $this->gateway()->syncOrder($this->order());

        self::assertSame('dolibarr', $reference->driver);
        self::assertSame('42', $reference->orderId);
        self::assertSame('5', $reference->customerId);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/thirdparties')) {
                return false;
            }

            // `client => 1` is what makes it a customer rather than a
            // prospect; without it no order can be attached.
            return $request['name'] === 'Ada Lovelace'
                && $request['client'] === 1
                && $request['email'] === 'ada@example.com'
                && $request['country_code'] === 'FR';
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/orders')) {
                return false;
            }

            $total = array_sum(array_map(
                static fn (array $line): float => $line['qty'] * $line['subprice'],
                $request['lines'],
            ));

            return $request['socid'] === 5
                && $request['ref_client'] === 'REF-1'
                && abs($total - 57.38) < 0.001;
        });
    }

    public function test_dolibarr_does_not_create_a_second_order_when_the_reference_is_already_there(): void
    {
        config()->set('erp.driver', 'dolibarr');
        config()->set('erp.dolibarr.url', 'http://dolibarr.test');
        config()->set('erp.dolibarr.api_key', 'key');

        Http::fake([
            'dolibarr.test/api/index.php/orders?*' => Http::response([
                ['id' => 42, 'socid' => 5, 'ref_client' => 'REF-1'],
            ]),
        ]);

        $reference = $this->gateway()->syncOrder($this->order());

        self::assertTrue($reference->alreadyPresent);
        self::assertSame('42', $reference->orderId);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    private function gateway(): ErpGateway
    {
        $this->app->forgetInstance(ErpGateway::class);

        return $this->app->make(ErpGateway::class);
    }

    /**
     * The arguments of the first Odoo call to `$model::$method`.
     *
     * Odoo's JSON-RPC puts everything in one envelope to one URL, so the only
     * way to tell the calls apart is to read what is inside.
     *
     * @return array<int, mixed>
     */
    private function odooCall(string $model, string $method): array
    {
        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            $body = json_decode((string) $request->body(), true);
            $args = $body['params']['args'] ?? [];

            if (($args[3] ?? null) === $model && ($args[4] ?? null) === $method) {
                /** @var array<int, mixed> $arguments */
                $arguments = $args[5] ?? [];

                return $arguments;
            }
        }

        self::fail("No Odoo call to {$model}::{$method} was recorded.");
    }

    private function order(): Order
    {
        $money = static fn (int $minor): Money => new Money(
            $minor, 'EUR', number_format($minor / 100, 2).' EUR',
        );

        return new Order(
            id: 1,
            reference: 'REF-1',
            placed: true,
            status: new OrderStatus('payment-received', 'Payment received'),
            lines: [
                new OrderLine(1, 'Navy Plain T-Shirt', null, 2, $money(1999), $money(3998), 'TSHIRT-NAVY'),
                new OrderLine(2, 'Canvas Tote Bag', null, 1, $money(1250), $money(1250), null),
            ],
            shippingAddress: null,
            billingAddress: new Address(
                countryId: 1,
                firstName: 'Ada',
                lastName: 'Lovelace',
                companyName: null,
                lineOne: '12 rue des Tests',
                lineTwo: null,
                city: 'Paris',
                state: null,
                postcode: '75001',
                contactEmail: 'ada@example.com',
                contactPhone: '+33100000000',
                countryIso: 'FR',
            ),
            subTotal: $money(5248),
            shippingTotal: $money(490),
            discountTotal: $money(0),
            taxTotal: $money(0),
            total: $money(5738),
        );
    }
}
