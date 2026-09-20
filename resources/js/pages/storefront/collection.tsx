import { ProductCard } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ProductSummary } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

interface CollectionProps {
    collectionSlug: string;
    products: ProductSummary[];
}

export default function CollectionPage({ collectionSlug, products }: CollectionProps) {
    const collectionName = collectionSlug.replace(/-/g, ' ');

    return (
        <StorefrontLayout>
            <Head title={collectionName} />

            <div className="text-muted-foreground mb-2 flex items-center gap-2 text-sm">
                <Link href={route('home')} className="hover:text-foreground">
                    Home
                </Link>
                <span>/</span>
                <span className="text-foreground capitalize">{collectionName}</span>
            </div>

            <h1 className="mb-6 text-2xl font-semibold capitalize">
                {collectionName} <span className="text-muted-foreground text-base font-normal">({products.length})</span>
            </h1>

            {products.length === 0 ? (
                <p className="text-muted-foreground">No products in this collection.</p>
            ) : (
                <div className="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                    {products.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            )}
        </StorefrontLayout>
    );
}
