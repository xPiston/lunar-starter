<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager
    |--------------------------------------------------------------------------
    |
    | The container id, `GTM-XXXXXXX`. Leave it empty and no Tag Manager script
    | is rendered at all - which is the state the test suite and a fresh
    | checkout run in.
    |
    | Tag Manager loads whatever tags you configure in its own interface,
    | including GA4. If you set up GA4 inside the container, leave
    | GOOGLE_ANALYTICS_ID empty below: running both means every page view is
    | counted twice, and nothing in the data says which half to throw away.
    |
    */

    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | The measurement id, `G-XXXXXXXXXX`, loaded directly through gtag.js
    | without going through Tag Manager. Use this *or* a GA4 tag inside the
    | container above, never both.
    |
    | A `UA-` property is not accepted: Universal Analytics stopped collecting
    | in 2023, and an id that silently sends nowhere is worse than no id.
    |
    */

    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    |
    | On by default. Google Consent Mode is initialised with every storage type
    | denied *before* any tag loads, so nothing is written to the visitor's
    | device until consent is given. In the EU that is not a preference, it is
    | the condition under which these scripts may run at all.
    |
    | With this on, the consent banner is mounted and nothing is collected
    | until the visitor accepts. With it off, no banner is shown at all - there
    | would be nothing to ask, the tags having been granted everything before
    | the page loaded.
    |
    | Setting this to false loads the tags with full consent from the first
    | byte. It is the configuration that produces complete reports and the one
    | a French shop gets fined for. The choice is deliberately yours, and
    | deliberately not the default.
    |
    */

    'require_consent' => env('ANALYTICS_REQUIRE_CONSENT', true),

];
