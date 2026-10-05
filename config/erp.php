<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | ERP Synchronisation
    |--------------------------------------------------------------------------
    |
    | Off by default, and that is the point: a shop without an ERP should not
    | have to disable anything, configure anything, or run a service it does
    | not use. `none` binds a gateway that does nothing, so the code path is
    | identical whether or not a business system is listening - there is no
    | `if (erp_enabled)` anywhere outside this file.
    |
    | Supported: "none", "odoo", "dolibarr".
    |
    | When a driver IS set, a paid order is pushed to it by a queued job (see
    | App\Jobs\SyncOrderToErp), so checkout never waits on an ERP and a slow
    | or sulking one costs a retry rather than a sale.
    |
    */

    'driver' => env('ERP_DRIVER', 'none'),

    /*
    | How many times the job tries before giving up, and how long it waits
    | between attempts. The backoff is deliberately long: the failure this is
    | built for is an ERP that is down or restarting, which is measured in
    | minutes, not in milliseconds. A failed job keeps the order - nothing is
    | lost, it lands in `failed_jobs` for a human to replay.
    */

    'retries' => (int) env('ERP_SYNC_RETRIES', 5),

    'backoff_seconds' => [60, 300, 900, 3600],

    /*
    | Seconds to wait on the ERP before calling it unreachable. ERPs are not
    | fast - Odoo computing taxes on a large order takes its time - so this is
    | generous compared to a normal API timeout.
    */

    'timeout_seconds' => (int) env('ERP_TIMEOUT_SECONDS', 30),

    'odoo' => [

        /*
        | The Odoo instance and the database inside it. One Odoo server can
        | host several databases, and the API will not guess which one: the
        | name is the one shown in the database selector at sign-in.
        */

        'url' => env('ODOO_URL', 'http://localhost:8069'),
        'database' => env('ODOO_DATABASE'),

        /*
        | Username, then an API KEY rather than the account password -
        | generate one under Preferences > Account Security. Odoo accepts
        | either in the same field, but a key can be revoked on its own and
        | cannot sign in to the web interface.
        */

        'username' => env('ODOO_USERNAME'),
        'api_key' => env('ODOO_API_KEY'),

        /*
        | Orders arrive as quotations (`draft`). Set this to confirm them into
        | real sales orders on arrival, which also reserves stock.
        |
        | Left off on purpose: a confirmed order in Odoo is a commitment, and
        | most shops want a human to look at the first few before the ERP
        | starts moving stock on its own.
        */

        'confirm_orders' => (bool) env('ODOO_CONFIRM_ORDERS', false),

    ],

    'dolibarr' => [

        /*
        | The base URL of the Dolibarr instance, WITHOUT /api/index.php -
        | the adapter appends it. Both forms are seen in the wild depending on
        | whether the API is behind a rewrite, so the adapter normalises
        | whichever is given.
        */

        'url' => env('DOLIBARR_URL', 'http://localhost:8081'),

        /*
        | The API key of the Dolibarr USER the orders are created as: found on
        | that user's card, under the API key tab, once the API module is
        | enabled in Home > Setup > Modules.
        */

        'api_key' => env('DOLIBARR_API_KEY'),

        /*
        | Dolibarr orders start as drafts (status 0). Set this to validate
        | them on arrival, which assigns the definitive order number.
        |
        | Same reasoning as Odoo's: a validated order is one a human can no
        | longer quietly correct.
        */

        'validate_orders' => (bool) env('DOLIBARR_VALIDATE_ORDERS', false),

    ],

];
