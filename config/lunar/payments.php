<?php

return [

    'default' => env('PAYMENTS_TYPE', 'stripe'),

    'types' => [
        'cash-in-hand' => [
            'driver' => 'offline',
            'authorized' => 'payment-offline',
        ],
        'stripe' => [
            'driver' => 'stripe',
            'authorized' => 'payment-received',
        ],
    ],

];
