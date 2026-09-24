<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Order Status Notifications
    |--------------------------------------------------------------------------
    |
    | Lunar tracks an order through the statuses configured in
    | config/lunar/orders.php, and staff move it along from the admin panel.
    | This is what tells the customer about it - otherwise the shop goes
    | silent after the order confirmation and they find out by coming back to
    | look.
    |
    */

    'enabled' => (bool) env('ORDER_STATUS_NOTIFICATIONS_ENABLED', true),

    /*
    | Only the statuses listed here are announced, and each one says what the
    | email should read like. An allow-list rather than "notify on every
    | change": a shop adding an internal status (`awaiting-stock`,
    | `fraud-check`) to Lunar's config must not start emailing customers about
    | its own bookkeeping, and the wording of a customer-facing email is not
    | something to derive from a slug.
    |
    | The keys are Lunar status handles. `payment-received` is deliberately
    | absent: the order confirmation already covers that moment, and two
    | emails a minute apart read as a bug.
    */

    'statuses' => [

        'dispatched' => [
            'subject' => 'Your order is on its way',
            'headline' => 'It has shipped',
            'body' => 'Your order has left us and is on its way to you. Delivery usually takes two to four working days.',
        ],

        'delivered' => [
            'subject' => 'Your order has been delivered',
            'headline' => 'Delivered',
            'body' => 'Our carrier reports that your order has arrived. If something looks wrong, reply to this email and we will sort it out.',
        ],

        'cancelled' => [
            'subject' => 'Your order has been cancelled',
            'headline' => 'Order cancelled',
            'body' => 'Your order has been cancelled and any payment taken will be refunded to the original method within a few working days.',
        ],

        'refunded' => [
            'subject' => 'Your refund is on its way',
            'headline' => 'Refunded',
            'body' => 'We have refunded your order to the payment method you used. Banks usually take a few working days to show it.',
        ],

    ],

    /*
    | How far an order can be through the shop's own process, for the progress
    | bar on the order page. Statuses absent from this list are shown as a
    | plain label instead: an order that was cancelled has not "reached step
    | 2 of 3", it left the path entirely.
    */

    'progress' => [
        'awaiting-payment' => 1,
        'payment-offline' => 1,
        'payment-received' => 2,
        'dispatched' => 3,
        'delivered' => 4,
    ],

];
