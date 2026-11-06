import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetClose, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import type { SharedData } from '@/types';
import type { CollectionSummary } from '@/types/storefront';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import { useState } from 'react';

interface NavLink {
    label: string;
    href: string;
}

/**
 * How many aisles fit across the bar before they stop being navigation.
 *
 * Two collections sat happily in the row; fourteen do not, and they do not
 * wrap either - the row has no flex-wrap, so they would simply run off the
 * side. Past this count the aisles move into one dropdown and the bar keeps
 * its shape, which is what every shop with a real department list does.
 */
const INLINE_COLLECTIONS = 4;

/**
 * Collection links come from `navCollections`, shared by
 * App\Http\Middleware\HandleInertiaRequests on storefront routes only - hence
 * the fallbacks: any page rendered outside that group simply gets no links
 * instead of crashing.
 *
 * News only appears once an article has been published: a shop that doesn't
 * write any shouldn't carry a permanently empty tab.
 */
function useCollectionLinks(): NavLink[] {
    const { navCollections } = usePage<SharedData>().props;

    return ((navCollections ?? []) as CollectionSummary[]).map((collection) => ({
        label: collection.name,
        href: route('collections.show', collection.slug),
    }));
}

/** Everything that is not an aisle: the fixed destinations around them. */
function useFixedLinks(): { before: NavLink[]; after: NavLink[] } {
    const { navContent } = usePage<SharedData>().props;

    return {
        before: [{ label: 'Home', href: route('home') }],
        after: [
            ...(navContent?.has_bundles ? [{ label: 'Bundles', href: route('bundles.index') }] : []),
            ...(navContent?.has_news ? [{ label: 'News', href: route('news.index') }] : []),
            { label: 'Track an order', href: route('orders.lookup') },
        ],
    };
}

function isActive(currentUrl: string, href: string): boolean {
    const path = href.replace(/^https?:\/\/[^/]+/, '') || '/';
    const current = currentUrl.split('?')[0];

    return path === '/' ? current === '/' : current.startsWith(path);
}

export function StorefrontNav() {
    const collections = useCollectionLinks();
    const { before, after } = useFixedLinks();
    const { url } = usePage<SharedData>();
    const [open, setOpen] = useState(false);

    // The sheet keeps every aisle flat: a phone scrolls, so there is nothing
    // to gain by hiding half of them behind a second tap.
    const sheetLinks = [...before, ...collections, ...after];
    const grouped = collections.length > INLINE_COLLECTIONS;
    const inline = grouped ? [...before, ...after] : [...before, ...collections, ...after];

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
                            {sheetLinks.map((link) => (
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
                    {inline.map((link) => (
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

                    {grouped ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className={`hover:text-foreground flex items-center gap-1 border-b-2 px-3 py-3 text-sm transition-colors ${
                                    collections.some((link) => isActive(url, link.href))
                                        ? 'border-primary text-foreground font-medium'
                                        : 'text-muted-foreground border-transparent'
                                }`}
                            >
                                Rayons
                                <ChevronDown className="size-4" aria-hidden />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="max-h-96 overflow-y-auto">
                                {collections.map((link) => (
                                    <DropdownMenuItem key={link.href} asChild>
                                        <Link href={link.href}>{link.label}</Link>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>
            </div>
        </nav>
    );
}
