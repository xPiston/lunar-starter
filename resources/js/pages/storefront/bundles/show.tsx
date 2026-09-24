import { BundleMosaic } from '@/components/storefront/bundle-mosaic';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Bundle } from '@/types/storefront';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { type FormEvent } from 'react';

// Same threshold as the product page: high enough to create urgency, low
// enough not to fire on every normal restock level.
const LOW_STOCK_THRESHOLD = 5;

export default function BundlePage({ bundle }: { bundle: Bundle }) {
    const { data, setData, post, processing } = useForm({ bundle_id: bundle.id, quantity: 1 });
    const errors = (usePage().props.errors ?? {}) as Partial<Record<string, string>>;
    const soldOut = bundle.available_stock !== null && bundle.available_stock === 0;

    function submit(event: FormEvent) {
        event.preventDefault();
        post(route('cart.bundles.store'), { preserveScroll: true });
    }

    return (
        <StorefrontLayout>
            <Head title={bundle.name} />

            <div className="text-muted-foreground mb-4 flex items-center gap-2 text-sm">
                <Link href={route('bundles.index')} className="hover:text-foreground">
                    Bundles
                </Link>
                <span>/</span>
                <span className="text-foreground">{bundle.name}</span>
            </div>

            <div className="grid gap-10 lg:grid-cols-2">
                {bundle.image_url ? (
                    <div className="bg-muted aspect-[4/3] overflow-hidden rounded-2xl border">
                        <img src={bundle.image_url} alt="" className="h-full w-full object-cover" />
                    </div>
                ) : (
                    <BundleMosaic items={bundle.items} className="aspect-[4/3] overflow-hidden rounded-2xl border" />
                )}

                <div>
                    <h1 className="text-2xl font-semibold">{bundle.name}</h1>

                    <div className="mt-3 flex flex-wrap items-baseline gap-3">
                        <span className="text-2xl font-semibold">{bundle.price.formatted}</span>
                        {bundle.savings.minor_amount > 0 && (
                            <>
                                <span className="text-muted-foreground line-through">{bundle.items_total.formatted}</span>
                                <Badge variant="secondary">Save {bundle.savings.formatted}</Badge>
                            </>
                        )}
                    </div>

                    {bundle.description && <p className="text-muted-foreground mt-4 text-sm leading-relaxed">{bundle.description}</p>}

                    <Card className="mt-6 p-5">
                        <h2 className="text-sm font-medium">What's in it</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            {bundle.items.map((item) => (
                                <li key={item.name} className="flex items-center gap-3">
                                    {item.image_url && (
                                        <img src={item.image_url} alt="" className="bg-muted size-10 shrink-0 rounded-md object-cover" />
                                    )}
                                    <span>
                                        {item.quantity}×{' '}
                                        {item.product_slug ? (
                                            <Link href={route('products.show', item.product_slug)} className="underline underline-offset-4">
                                                {item.name}
                                            </Link>
                                        ) : (
                                            item.name
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    {/* Availability is the scarcest part's, worked out by the
                        bundle itself - saying "3 left" when one component is
                        down to three is the honest number. */}
                    {soldOut ? (
                        <Badge variant="destructive" className="mt-6">
                            Sold out
                        </Badge>
                    ) : (
                        bundle.available_stock !== null &&
                        bundle.available_stock <= LOW_STOCK_THRESHOLD && (
                            <p className="text-muted-foreground mt-6 text-sm">Only {bundle.available_stock} left</p>
                        )
                    )}

                    <form onSubmit={submit} className="mt-6">
                        <div className="flex items-center gap-3">
                            <input
                                type="number"
                                min={1}
                                value={data.quantity}
                                onChange={(event) => setData('quantity', Math.max(1, Number(event.target.value)))}
                                aria-label="Quantity"
                                className="border-input h-10 w-20 rounded-md border px-3 text-sm"
                            />
                            <Button type="submit" size="lg" className="flex-1" disabled={processing || soldOut}>
                                <ShoppingCart />
                                {soldOut ? 'Sold out' : 'Add bundle to cart'}
                            </Button>
                        </div>
                        {errors.quantity && <p className="text-destructive mt-2 text-sm">{errors.quantity}</p>}
                    </form>
                </div>
            </div>
        </StorefrontLayout>
    );
}
