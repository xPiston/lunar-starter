<?php

declare(strict_types=1);

namespace App\Application\Checkout;

use App\Domain\Checkout\CheckoutFailedException;
use App\Domain\Checkout\Order;
use App\Domain\Checkout\Port\CheckoutGateway;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

final readonly class CompleteCheckout
{
    public function __construct(private CheckoutGateway $gateway) {}

    /**
     * @throws CheckoutFailedException
     */
    public function handle(string $paymentIntentId): Order
    {
        $order = $this->gateway->completePayment($paymentIntentId);

        // Laravel's Mail facade IS the port here: swapping providers (SMTP,
        // Postmark, SES...) is a MAIL_MAILER config change, not a code change,
        // so there's no need for a dedicated port/adapter the way there is
        // for Lunar. Queued so checkout doesn't wait on an SMTP round-trip.
        if ($order->billingAddress?->contactEmail) {
            Mail::to($order->billingAddress->contactEmail)->queue(new OrderConfirmationMail($order));
        }

        return $order;
    }
}
