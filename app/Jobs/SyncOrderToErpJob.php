<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Erp\SyncOrderToErp;
use App\Domain\Checkout\Order;
use App\Domain\Erp\ErpUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pushes a paid order to the ERP, off the checkout request.
 *
 * Queued for the same reason the confirmation email is: the customer has paid,
 * and nothing that happens afterwards is allowed to make that look like it
 * failed. An ERP mid-upgrade would otherwise turn a successful payment into a
 * 500 on the thank-you page, with the money already taken.
 *
 * The ORDER travels, not its id. The domain object is already built and
 * immutable, it serialises to the queue as plain data, and it is a snapshot of
 * what was actually bought - re-reading the order at retry time, perhaps after
 * staff edited it, would push something the customer never agreed to.
 */
final class SyncOrderToErpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public int $tries {
        get => max(1, (int) config('erp.retries', 5));
    }

    /**
     * Minutes, not seconds, and growing. The failure being waited out is an
     * ERP that is down or restarting; hammering it every ten seconds neither
     * helps it recover nor gets the order in any sooner.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        /** @var int[] $backoff */
        $backoff = config('erp.backoff_seconds', [60, 300, 900, 3600]);

        return $backoff;
    }

    /**
     * Only an unreachable ERP is worth retrying.
     *
     * A payload the ERP rejects on its merits - a mandatory field it wants and
     * this shop does not collect - is rejected identically on every attempt,
     * so it is failed here and now instead of after an hour of pretending. The
     * order is kept either way; what differs is how long a human waits to find
     * out there is something to fix.
     */
    public function handle(SyncOrderToErp $sync): void
    {
        try {
            $sync->handle($this->order);
        } catch (ErpUnavailableException $unreachable) {
            // Rethrown untouched: this is the one the backoff above is for.
            throw $unreachable;
        } catch (Throwable $refusal) {
            $this->fail($refusal);
        }
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Order could not be synchronised to the ERP.', [
            'order_reference' => $this->order->reference,
            'driver' => config('erp.driver'),
            'retryable' => $exception instanceof ErpUnavailableException,
            'exception' => $exception->getMessage(),
        ]);
    }
}
