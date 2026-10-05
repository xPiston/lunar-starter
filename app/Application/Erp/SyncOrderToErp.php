<?php

declare(strict_types=1);

namespace App\Application\Erp;

use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpReference;
use App\Domain\Erp\Port\ErpGateway;
use Illuminate\Support\Facades\Log;

/**
 * Hands a paid order to the business system.
 *
 * Thin on purpose. Everything hard about talking to an ERP - authenticating,
 * matching a customer, shaping a line - belongs to the adapter, because every
 * ERP does it differently. What belongs HERE is the part that is the same
 * whichever one is plugged in: that it happened, and that the result is
 * written down somewhere a human can find it.
 *
 * That log line is not decoration. When an accountant asks in three weeks why
 * order ABC-123 is not in Odoo, the answer is either "it is, as SO042" or
 * "it never arrived", and only one of those is a bug.
 */
final readonly class SyncOrderToErp
{
    public function __construct(private ErpGateway $erp) {}

    public function handle(Order $order): ErpReference
    {
        $reference = $this->erp->syncOrder($order);

        if ($reference->driver === 'none') {
            return $reference;
        }

        Log::info('Order synchronised to the ERP.', [
            'order_reference' => $order->reference,
            ...$reference->toArray(),
        ]);

        return $reference;
    }
}
