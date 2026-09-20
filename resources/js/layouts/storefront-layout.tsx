import AppLogoIcon from '@/components/app-logo-icon';
import { StorefrontNav } from '@/components/storefront/storefront-nav';
import { ThemeToggle } from '@/components/storefront/theme-toggle';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { PackageSearch, Search, ShoppingCart } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';

interface StorefrontLayoutProps {
    children: ReactNode;
}

/**
 * Storefront layout (home, collection, product page, cart) - distinct from
 * `app-layout.tsx` (the authenticated dashboard shell, with its sidebar):
 * these are two different experiences that don't need to share visual
 * chrome. Light/dark follows the shared `use-appearance` preference
 * (`<ThemeToggle />` in the header writes the same localStorage key as the
 * dashboard's own dropdown), so every colour here goes through theme tokens
 * rather than being hardcoded for one mode.
 *
 * The cart badge reads `cartItemCount` from Inertia's shared props. That
 * only works because the count is fetched side-effect-free (it never
 * creates a cart, see `CartGateway::currentItemCount()`); resolving the
 * cart itself on every response would persist a row on every dashboard and
 * auth page too. Collection links are still kept out of the header nav for
 * the cost reason that no longer applies to the badge: they'd need a real
 * query on every response, so the chip row on Home covers them instead.
 */
export default function StorefrontLayout({ children }: StorefrontLayoutProps) {
    const { props, url } = usePage<SharedData>();
    const { auth, cartItemCount } = props;
    const currentQuery = new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? '';
    const [searchTerm, setSearchTerm] = useState(currentQuery);

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        router.get(route('search'), searchTerm ? { q: searchTerm } : {});
    }

    return (
        <div className="bg-background text-foreground flex min-h-svh flex-col">
            <header>
                <div className="mx-auto flex max-w-6xl items-center gap-6 px-6 py-4">
                    <Link href={route('home')} className="flex shrink-0 items-center gap-2 font-semibold">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-full">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span>Shop</span>
                    </Link>

                    <form onSubmit={submitSearch} className="flex-1">
                        <div className="border-input bg-secondary/40 focus-within:ring-ring/50 flex h-10 max-w-md items-center gap-2 rounded-full border px-4 focus-within:ring-2">
                            <Search className="text-muted-foreground size-4 shrink-0" />
                            <input
                                type="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search products…"
                                aria-label="Search products"
                                className="w-full bg-transparent text-sm outline-none"
                            />
                        </div>
                    </form>

                    <div className="flex shrink-0 items-center gap-2">
                        <ThemeToggle />

                        <Button variant="ghost" size="icon" className="relative rounded-full" asChild>
                            <Link href={route('cart.show')} aria-label={cartItemCount > 0 ? `Cart (${cartItemCount} items)` : 'Cart'}>
                                <ShoppingCart className="size-5" />
                                {cartItemCount > 0 && (
                                    <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex size-4.5 items-center justify-center rounded-full text-[10px] font-semibold">
                                        {cartItemCount > 99 ? '99+' : cartItemCount}
                                    </span>
                                )}
                            </Link>
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button aria-label="Account" className="rounded-full">
                                    <Avatar className="size-9">
                                        <AvatarFallback>
                                            <PackageSearch className="size-4" />
                                        </AvatarFallback>
                                    </Avatar>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {auth.user ? (
                                    <>
                                        <DropdownMenuItem asChild>
                                            <Link href={route('account.orders')}>My orders</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem asChild>
                                            <Link href={route('logout')} method="post" as="button" className="w-full">
                                                Log out
                                            </Link>
                                        </DropdownMenuItem>
                                    </>
                                ) : (
                                    <>
                                        <DropdownMenuItem asChild>
                                            <Link href={route('login')}>Log in</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link href={route('register')}>Create an account</Link>
                                        </DropdownMenuItem>
                                    </>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href={route('orders.lookup')}>Track an order</Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <StorefrontNav />
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-8">{children}</main>

            <footer className="border-border/60 border-t">
                <div className="mx-auto grid max-w-6xl gap-8 px-6 py-10 sm:grid-cols-3">
                    <div>
                        <Link href={route('home')} className="flex items-center gap-2 font-semibold">
                            <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-full">
                                <AppLogoIcon className="size-4 fill-current" />
                            </span>
                            <span>Shop</span>
                        </Link>
                        <p className="text-muted-foreground mt-3 text-sm">Hexagonal e-commerce template - Laravel, Lunar &amp; Inertia/React.</p>
                    </div>

                    <div>
                        <h3 className="mb-3 text-sm font-medium">Explore</h3>
                        <ul className="text-muted-foreground space-y-2 text-sm">
                            <li>
                                <Link href={route('home')} className="hover:text-foreground">
                                    Home
                                </Link>
                            </li>
                            <li>
                                <Link href={route('orders.lookup')} className="hover:text-foreground">
                                    Track an order
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="mb-3 text-sm font-medium">Legal</h3>
                        <ul className="text-muted-foreground space-y-2 text-sm">
                            <li>
                                <Link href={route('legal.terms')} className="hover:text-foreground">
                                    Terms of Service
                                </Link>
                            </li>
                            <li>
                                <Link href={route('legal.privacy')} className="hover:text-foreground">
                                    Privacy Policy
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <div className="border-border/60 text-muted-foreground border-t px-6 py-4 text-center text-xs">
                    &copy; {new Date().getFullYear()} Shop. Built with Laravel, Lunar &amp; Inertia.
                </div>
            </footer>
        </div>
    );
}
