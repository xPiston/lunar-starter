import { Button } from '@/components/ui/button';
import { Sheet, SheetClose, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import type { SharedData } from '@/types';
import type { CollectionSummary } from '@/types/storefront';
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';

interface NavLink {
    label: string;
    href: string;
}

/**
 * Collection links come from `navCollections`, shared by
 * App\Http\Middleware\HandleInertiaRequests on storefront routes only - hence
 * the fallbacks: any page rendered outside that group simply gets no links
 * instead of crashing.
 *
 * News only appears once an article has been published: a shop that doesn't
 * write any shouldn't carry a permanently empty tab.
 */
function useNavLinks(): NavLink[] {
    const { navCollections, navContent } = usePage<SharedData>().props;
    const collections = (navCollections ?? []) as CollectionSummary[];

    return [
        { label: 'Home', href: route('home') },
        ...collections.map((collection) => ({
            label: collection.name,
            href: route('collections.show', collection.slug),
        })),
        ...(navContent?.has_bundles ? [{ label: 'Bundles', href: route('bundles.index') }] : []),
        ...(navContent?.has_news ? [{ label: 'News', href: route('news.index') }] : []),
        { label: 'Track an order', href: route('orders.lookup') },
    ];
}

function isActive(currentUrl: string, href: string): boolean {
    const path = href.replace(/^https?:\/\/[^/]+/, '') || '/';
    const current = currentUrl.split('?')[0];

    return path === '/' ? current === '/' : current.startsWith(path);
}

export function StorefrontNav() {
    const links = useNavLinks();
    const { url } = usePage<SharedData>();
    const [open, setOpen] = useState(false);

    return (
        <nav className="border-border/60 border-t border-b">
            <div className="mx-auto flex max-w-6xl items-center gap-1 px-6">
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger asChild>
                        <Button variant="ghost" size="icon" className="my-1 sm:hidden" aria-label="Open menu">
                            <Menu className="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" className="w-64">
                        <SheetTitle className="mb-4">Menu</SheetTitle>
                        <div className="flex flex-col">
                            {links.map((link) => (
                                <SheetClose asChild key={link.href}>
                                    <Link
                                        href={link.href}
                                        className={`hover:bg-accent rounded-md px-3 py-2 text-sm ${
                                            isActive(url, link.href) ? 'bg-accent font-medium' : ''
                                        }`}
                                    >
                                        {link.label}
                                    </Link>
                                </SheetClose>
                            ))}
                        </div>
                    </SheetContent>
                </Sheet>

                <div className="hidden items-center gap-1 sm:flex">
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className={`hover:text-foreground border-b-2 px-3 py-3 text-sm transition-colors ${
                                isActive(url, link.href) ? 'border-primary text-foreground font-medium' : 'text-muted-foreground border-transparent'
                            }`}
                        >
                            {link.label}
                        </Link>
                    ))}
                </div>
            </div>
        </nav>
    );
}
