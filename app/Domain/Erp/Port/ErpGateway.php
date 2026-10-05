<?php

declare(strict_types=1);

namespace App\Domain\Erp\Port;

use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpReference;
use App\Domain\Erp\ErpUnavailableException;

/**
 * OUTBOUND port of the Erp context: the shop hands a paid order to whatever
 * system the business actually runs on.
 *
 * ONE method, not two. Pushing a customer and pushing an order look like two
 * steps from here, but no ERP lets you do them independently: an order needs a
 * customer id, and every ERP has its own idea of how a customer is found,
 * matched or created. Splitting them would mean this interface deciding the
 * order of operations for systems it knows nothing about.
 *
 * It takes `Checkout\Order` rather than a model of its own. An ERP sync is not
 * a new reading of what an order is - it is the same order, sent elsewhere -
 * and a parallel `ErpOrder` would be the same fields with a mapper in between,
 * drifting the first time one side gained a field.
 *
 * Implementations MUST be idempotent: the job that calls this retries, and a
 * retry must not produce a second order in the ERP. Both shipped adapters get
 * that by asking the ERP whether it already holds this shop's reference rather
 * than by remembering locally - an ERP that was restored from backup, or an
 * order pushed by hand, then still gives the right answer.
 */
interface ErpGateway
{
    /**
     * @throws ErpUnavailableException when the ERP could not be reached or
     *                                 refused the call. The caller retries;
     *                                 anything else is a bug and should
     *                                 surface as one.
     */
    public function syncOrder(Order $order): ErpReference;
}
