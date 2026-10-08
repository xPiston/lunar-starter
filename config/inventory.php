<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Statuses that put the units back
    |--------------------------------------------------------------------------
    |
    | An order moved into one of these returns what it took to the shelf, and
    | moving it back out takes it again. Anything not listed leaves stock
    | alone: a dispatched order has not released anything, and neither has one
    | sitting in an internal `fraud-check` a shop invented for itself.
    |
    | An allow-list rather than "restock on anything that looks final",
    | because a slug is not a decision. The handles must also exist in
    | config/lunar/orders.php, or staff have no way to set them.
    |
    */

    'restock_statuses' => ['cancelled', 'refunded'],

];
