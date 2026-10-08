import { Button } from '@/components/ui/button';
import { CONSENT_REOPEN_EVENT, denyAnalyticsConsent, grantAnalyticsConsent, readConsentChoice } from '@/lib/analytics';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * The consent banner.
 *
 * Rendered outside the Inertia page tree (see resources/js/app.tsx) so it
 * survives navigation and appears on every page, storefront or dashboard,
 * rather than being a property of one layout.
 *
 * It is only mounted when there is something to consent to: a tag configured
 * *and* `ANALYTICS_REQUIRE_CONSENT` on. A banner on a site that sets no
 * analytics cookie either way is theatre, and the kind that teaches people to
 * click "accept" without reading.
 *
 * Refusing is one click, exactly like accepting. That is not a design
 * preference - a banner where "accept" is a button and "refuse" is three menus
 * deep is the thing regulators actually fine.
 */
export function CookieConsent({ required }: { required: boolean }) {
    // Never open on the first render: the stored answer is read in an effect,
    // and rendering the banner before that read flashes it at every visitor
    // who already answered.
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!required) {
            return;
        }

        if (readConsentChoice() === null) {
            setOpen(true);
        }

        // The footer's "Cookies" link, for a visitor changing their mind.
        const reopen = () => setOpen(true);
        window.addEventListener(CONSENT_REOPEN_EVENT, reopen);

        return () => window.removeEventListener(CONSENT_REOPEN_EVENT, reopen);
    }, [required]);

    if (!required || !open) {
        return null;
    }

    function answer(accepted: boolean) {
        if (accepted) {
            grantAnalyticsConsent();
        } else {
            denyAnalyticsConsent();
        }

        setOpen(false);
    }

    return (
        <div role="region" aria-label="Cookie consent" className="fixed inset-x-0 bottom-0 z-50 p-4 sm:p-6">
            <div className="border-border bg-background mx-auto flex max-w-3xl flex-col gap-4 rounded-xl border p-5 shadow-lg sm:flex-row sm:items-center">
                <p className="text-muted-foreground flex-1 text-sm">
                    We use cookies to measure how the shop is used. Nothing is stored until you accept, and refusing keeps every part of the shop
                    working.{' '}
                    <Link href={route('legal.privacy')} className="text-foreground underline underline-offset-4">
                        Privacy Policy
                    </Link>
                </p>

                <div className="flex shrink-0 gap-2">
                    <Button variant="outline" onClick={() => answer(false)}>
                        Refuse
                    </Button>
                    <Button onClick={() => answer(true)}>Accept</Button>
                </div>
            </div>
        </div>
    );
}
