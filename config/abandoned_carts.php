<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Abandoned Cart Reminders
    |--------------------------------------------------------------------------
    |
    | One email to whoever left items behind, with a link that puts the cart
    | back in their browser. Sent by `carts:send-abandoned-reminders`, which
    | routes/console.php schedules hourly - so nothing goes out unless the
    | scheduler is actually running (see docs/deployment.md).
    |
    */

    'enabled' => (bool) env('ABANDONED_CART_REMINDERS_ENABLED', true),

    /*
    | How long a cart has to sit untouched before it counts as abandoned.
    | Measured from the last activity on the cart OR any of its lines, not
    | just the cart row: Lunar's cart lines don't touch their cart's
    | timestamp, so a cart someone is actively filling would otherwise look
    | idle since the moment it was created.
    |
    | Too short and it arrives while they're still shopping; too long and
    | they've bought it elsewhere. An hour is the usual starting point.
    */

    'idle_after_minutes' => (int) env('ABANDONED_CART_IDLE_MINUTES', 60),

    /*
    | Carts older than this are left alone. Without it, switching the feature
    | on for the first time would email everyone who ever abandoned a cart,
    | including people who did so months ago.
    */

    'ignore_older_than_days' => (int) env('ABANDONED_CART_MAX_AGE_DAYS', 7),

    /*
    | How long the recovery link in the email stays valid. It is a signed URL,
    | so this is enforced by the signature itself - an expired one cannot be
    | replayed, whoever holds it.
    */

    'link_lifetime_days' => (int) env('ABANDONED_CART_LINK_DAYS', 7),

    /*
    | Carts handled per run. The command is scheduled hourly, so this caps the
    | burst of queued mails rather than the total: what doesn't fit is picked
    | up on the next run, still abandoned.
    */

    'batch_size' => (int) env('ABANDONED_CART_BATCH_SIZE', 100),

];
