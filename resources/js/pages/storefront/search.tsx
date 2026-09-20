import { ProductCard } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ProductSummary } from '@/types/storefront';
import { Head } from '@inertiajs/react';

interface SearchPageProps {
    query: string;
    products: ProductSummary[];
}

export default function SearchPage({ query, products }: SearchPageProps) {
    return (
        <StorefrontLayout>
            <Head title={query ? `Search: ${query}` : 'Search'} />

            <h1 className="mb-6 text-2xl font-semibold">
                {query ? `Results for "${query}"` : 'Search'}
                {query !== '' && <span className="text-muted-foreground ml-2 text-base font-normal">({products.length})</span>}
            </h1>

            {query === '' ? (
                <p className="text-muted-foreground">Type something in the search bar above to find products.</p>
            ) : products.length === 0 ? (
                <p className="text-muted-foreground">No products match "{query}".</p>
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
