<?php

declare(strict_types=1);

namespace App\Infrastructure\Erp;

use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpReference;
use App\Domain\Erp\Port\ErpGateway;

/**
 * The adapter for a shop that has no ERP, which is most of them.
 *
 * This is what makes the feature OPTIONAL without a single conditional in the
 * application. `CompleteCheckout` pushes every paid order the same way;
 * whether anything is listening is a binding in the composition root, decided
 * once by `config('erp.driver')`.
 *
 * The alternative - an `if ($erpEnabled)` around the dispatch - would put the
 * same question in every caller, and get it wrong in one of them eventually.
 * A null object answers the question once.
 */
final readonly class NullErpGateway implements ErpGateway
{
    public function syncOrder(Order $order): ErpReference
    {
        // Echoes the shop's own reference back rather than inventing ids: a
        // caller logging the result then prints something true in both
        // configurations.
        return new ErpReference(
            driver: 'none',
            customerId: '',
            orderId: $order->reference,
            alreadyPresent: true,
        );
    }
}
