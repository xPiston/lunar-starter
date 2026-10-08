{{--
    Tag Manager's <noscript> half. It belongs immediately after <body>, which
    is why it is a second partial rather than part of the <head> one.

    It only ever matters for a visitor with JavaScript disabled - who will not
    be running the consent script either, so this iframe loads regardless of
    the consent setting. It sets no cookie of its own; whatever the container
    is configured to fire is what decides.
--}}
@if ($analytics->googleTagManagerId)
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id={{ $analytics->googleTagManagerId }}"
                height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
@endif
