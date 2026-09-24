<?php

declare(strict_types=1);

namespace App\Infrastructure\Lunar\Checkout;

use App\Application\Checkout\NotifyCustomerOfOrderStatus;
use App\Domain\Checkout\OrderStatus;
use App\Domain\Checkout\OrderStatusChange;
use Lunar\Models\Order as LunarOrder;
use Lunar\Models\OrderAddress;

/**
 * Notices that an order changed status and hands the fact to the application.
 *
 * An Eloquent observer because Lunar fires no event for this: staff move an
 * order along from the Filament panel, which is a plain model update. Sitting
 * on the model rather than on the admin screen means a status changed by a
 * console command, a webhook or a queued job is announced just the same.
 *
 * It decides nothing. Whether a status is worth an email, and what that email
 * says, belongs to NotifyCustomerOfOrderStatus.
 */
final readonly class OrderStatusObserver
{
    public function __construct(private NotifyCustomerOfOrderStatus $notify) {}

    public function updated(LunarOrder $order): void
    {
        // `wasChanged`, not `isDirty`: this runs after the save, and only a
        // status that actually moved counts. Re-saving an order with the same
        // status - which the admin panel does on any edit - must not send a
        // second email.
        if (! $order->wasChanged('status')) {
            return;
        }

        // An order that never completed checkout has no customer waiting on
        // it, and its address may be half-typed.
        if ($order->placed_at === null) {
            return;
        }

        $email = $this->emailFor($order);

        if ($email === null) {
            return;
        }

        $this->notify->handle(new OrderStatusChange(
            reference: (string) $order->reference,
            status: new OrderStatus((string) $order->status, (string) $order->status_label),
            email: $email,
            firstName: $this->address($order)?->first_name,
        ));
    }

    private function emailFor(LunarOrder $order): ?string
    {
        $email = trim((string) $this->address($order)?->contact_email);

        return $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            ? null
            : $email;
    }

    /**
     * Billing first: it is the address the payment was taken against, so its
     * contact details are the ones that were verified.
     */
    private function address(LunarOrder $order): ?OrderAddress
    {
        /** @var ?OrderAddress $billing */
        $billing = $order->billingAddress;
        /** @var ?OrderAddress $shipping */
        $shipping = $order->shippingAddress;

        return $billing ?? $shipping;
    }
}
