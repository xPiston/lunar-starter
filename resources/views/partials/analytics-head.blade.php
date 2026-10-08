{{--
    Everything Google needs in <head>, in the one order that works.

    Consent defaults have to be in place before a tag loads, not after: a tag
    that boots with no consent state assumes it has consent. So the consent
    block comes first, then the container, then gtag.

    @see App\Http\Analytics\Tags — ids are validated before they get here.
--}}
@if ($analytics->enabled())
    @if ($analytics->requireConsent)
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'analytics_storage': 'denied',
                'functionality_storage': 'denied',
                'personalization_storage': 'denied',
                'security_storage': 'granted',
                'wait_for_update': 500
            });
        </script>
    @endif

    @if ($analytics->googleTagManagerId)
        {{-- Tag Manager's own snippet, unchanged apart from the id. --}}
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ $analytics->googleTagManagerId }}');
        </script>
    @endif

    @if ($analytics->googleAnalyticsId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analytics->googleAnalyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            {{-- send_page_view stays on: it fires the first view from the tag
                 itself, so a visitor whose JavaScript bundle fails is still
                 counted. Every view after that comes from the Inertia
                 listener in resources/js/lib/analytics.ts, which skips the
                 url it was loaded on so the first one is not counted twice. --}}
            gtag('config', '{{ $analytics->googleAnalyticsId }}');
        </script>
    @endif
@endif
