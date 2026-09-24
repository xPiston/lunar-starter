import { BundleMosaic } from '@/components/storefront/bundle-mosaic';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import type { Bundle } from '@/types/storefront';
import { Link } from '@inertiajs/react';

export function BundleCard({ bundle }: { bundle: Bundle }) {
    const soldOut = bundle.available_stock !== null && bundle.available_stock === 0;

    return (
        <Card className="hover:border-foreground/20 overflow-hidden py-0 transition-colors">
            <Link href={route('bundles.show', bundle.slug)} className="block">
                {/* The shop's own picture if it uploaded one, otherwise the
                    contents laid out - never one part standing in for the box. */}
                {bundle.image_url ? (
                    <div className="bg-muted aspect-[3/2] overflow-hidden">
                        <img src={bundle.image_url} alt="" className="h-full w-full object-cover" />
                    </div>
                ) : (
                    <BundleMosaic items={bundle.items} className="aspect-[3/2]" />
                )}
                <div className="p-5">
                    <h2 className="font-medium">{bundle.name}</h2>
                    <ul className="text-muted-foreground mt-2 space-y-0.5 text-sm">
                        {bundle.items.map((item) => (
                            <li key={item.name}>
                                {item.quantity}× {item.name}
                            </li>
                        ))}
                    </ul>
                    <div className="mt-4 flex flex-wrap items-baseline gap-2">
                        <span className="text-lg font-semibold">{bundle.price.formatted}</span>
                        {bundle.savings.minor_amount > 0 && (
                            <>
                                <span className="text-muted-foreground text-sm line-through">{bundle.items_total.formatted}</span>
                                <Badge variant="secondary">Save {bundle.savings.formatted}</Badge>
                            </>
                        )}
                        {soldOut && <Badge variant="destructive">Sold out</Badge>}
                    </div>
                </div>
            </Link>
        </Card>
    );
}
