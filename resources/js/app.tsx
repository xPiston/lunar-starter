import '../css/app.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { CookieConsent } from './components/storefront/cookie-consent';
import { initializeTheme } from './hooks/use-appearance';
import { initializeAnalytics } from './lib/analytics';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // Inertia 3 narrowed what a resolver may return: a component, or a promise
    // of one - no longer a promise of the whole module. Hence the typed glob
    // and the unwrapping of `default` that version 2 did for us.
    resolve: (name) =>
        resolvePageComponent<{ default: ResolvedComponent }>(
            `./pages/${name}.tsx`,
            import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx'),
        ).then((page) => page.default),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // The banner sits beside the page rather than inside a layout: it has
        // to appear wherever the tags run, which is every page, and it has no
        // business being re-mounted on each navigation. Being outside the
        // page tree, it cannot call usePage() - the flag comes from the
        // initial props, and it is configuration, so it cannot change between
        // two pages anyway.
        root.render(
            <>
                <App {...props} />
                <CookieConsent required={props.initialPage.props.analyticsConsentRequired === true} />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Inertia navigations are not page loads, so the Google tags never see them.
// No-op unless a tag was configured server-side.
initializeAnalytics();
