import { ListingControls } from '@/components/storefront/listing-controls';
import { Pagination } from '@/components/storefront/pagination';
import { ProductCard } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ListingFilters, ProductListing, SortOption } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

interface CollectionProps {
    collectionSlug: string;
    listing: ProductListing;
    filters: ListingFilters;
    sortOptions: SortOption[];
}

export default function CollectionPage({ collectionSlug, listing, filters, sortOptions }: CollectionProps) {
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
                {collectionName} <span className="text-muted-foreground text-base font-normal">({listing.total})</span>
            </h1>

            <ListingControls filters={filters} sortOptions={sortOptions} total={listing.total} />

            {listing.items.length === 0 ? (
                <p className="text-muted-foreground">No products match what you asked for.</p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                        {listing.items.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>

                    <Pagination page={listing.page} lastPage={listing.last_page} />
                </>
            )}
        </StorefrontLayout>
    );
}
