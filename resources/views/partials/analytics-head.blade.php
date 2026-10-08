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

            {{-- A previous visit's answer, re-applied before the tags load
                 rather than after the React banner has mounted: a visitor who
                 accepted last week should not spend the first second of every
                 page refused. The key is the one in
                 resources/js/lib/analytics.ts - change it in both places or
                 in neither. --}}
            try {
                if (window.localStorage.getItem('analytics-consent') === 'granted') {
                    gtag('consent', 'update', {
                        'ad_storage': 'granted',
                        'ad_user_data': 'granted',
                        'ad_personalization': 'granted',
                        'analytics_storage': 'granted',
                        'functionality_storage': 'granted',
                        'personalization_storage': 'granted'
                    });
                }
            } catch (e) {
                // Private browsing, blocked storage: no stored answer, so the
                // defaults above stand and the banner asks again.
            }
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
