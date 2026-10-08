import { router } from '@inertiajs/react';

/**
 * Page views for a storefront that never reloads.
 *
 * Both Google tags count a page view when the document loads. Inertia replaces
 * the page component and pushes a history entry instead, so without this file
 * a visitor who browses a collection, opens three products and checks out is
 * recorded as having looked at one page.
 *
 * Nothing here loads a tag or decides whether one should exist - that is the
 * server's job, in config/analytics.php and resources/views/partials. This
 * only reports navigations to whatever happens to be listening, and does
 * nothing at all when nothing is.
 */

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
    }
}

/**
 * The url the document was served with - already counted by the tag itself.
 *
 * Inertia fires `navigate` on history restores and on the initial page in some
 * paths, so the url is the only reliable way to tell a real navigation from a
 * re-announcement of the one we are already on. Comparing the full url means a
 * filter change that rewrites the query string still counts, which is what a
 * shop wants: `/t-shirts?size=m` is a different page from `/t-shirts`.
 */
let lastTrackedUrl = typeof window === 'undefined' ? null : window.location.href;

/**
 * Report one page view, to Tag Manager and to gtag if either is present.
 *
 * Pushing to `dataLayer` is safe even before Tag Manager has loaded: the
 * snippet in the <head> creates the array first, and the container replays
 * whatever is already in it.
 */
export function trackPageView(url: string = window.location.href): void {
    const path = new URL(url, window.location.origin).pathname;

    window.dataLayer?.push({
        event: 'page_view',
        page_location: url,
        page_path: path,
        page_title: document.title,
    });

    window.gtag?.('event', 'page_view', {
        page_location: url,
        page_path: path,
        page_title: document.title,
    });
}

/**
 * Tell Google the visitor accepted. Call this from a consent banner.
 *
 * Only has any effect when `ANALYTICS_REQUIRE_CONSENT` is on, since that is
 * what denies the storage types in the first place. With consent not required,
 * the tags were already granted everything and this is a no-op by design -
 * a banner that calls it either way stays correct.
 */
export function grantAnalyticsConsent(): void {
    window.gtag?.('consent', 'update', {
        ad_storage: 'granted',
        ad_user_data: 'granted',
        ad_personalization: 'granted',
        analytics_storage: 'granted',
        functionality_storage: 'granted',
        personalization_storage: 'granted',
    });
}

/**
 * The opposite, for the "refuse" button and for a visitor changing their mind.
 */
export function denyAnalyticsConsent(): void {
    window.gtag?.('consent', 'update', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        functionality_storage: 'denied',
        personalization_storage: 'denied',
    });
}

/**
 * How long to wait for the incoming page's title before reporting anyway.
 *
 * Only reached when the new page's title is identical to the old one, since
 * anything else resolves as soon as the title changes - measured at ~40ms.
 */
const TITLE_TIMEOUT_MS = 500;

/**
 * Run `report` once the document title belongs to the page we moved to.
 *
 * Inertia sets the title when the incoming page's <Head> mounts, which has not
 * happened when `navigate` fires. Deferring by a frame is not enough - measured
 * in a real browser, the title lands a frame or two later than that, and every
 * view gets filed under the title of the page you just left. So this waits for
 * the change rather than guessing how long it takes, and gives up after
 * TITLE_TIMEOUT_MS for the case where the two titles happen to be the same.
 */
function whenTitleSettles(previousTitle: string, report: () => void): void {
    if (document.title !== previousTitle) {
        report();

        return;
    }

    let reported = false;

    const finish = (): void => {
        if (reported) {
            return;
        }

        reported = true;
        observer.disconnect();
        window.clearTimeout(timer);
        report();
    };

    const observer = new MutationObserver(() => {
        if (document.title !== previousTitle) {
            finish();
        }
    });

    // The whole head, not the <title> element: Inertia's head manager replaces
    // that element rather than editing its text, so observing it watches a node
    // that is no longer in the document by the time it matters.
    observer.observe(document.head, { childList: true, subtree: true, characterData: true });

    const timer = window.setTimeout(finish, TITLE_TIMEOUT_MS);
}

/**
 * Start reporting Inertia navigations. Called once, from app.tsx.
 */
export function initializeAnalytics(): void {
    router.on('navigate', () => {
        const url = window.location.href;

        if (url === lastTrackedUrl) {
            return;
        }

        lastTrackedUrl = url;
        whenTitleSettles(document.title, () => trackPageView(url));
    });
}
