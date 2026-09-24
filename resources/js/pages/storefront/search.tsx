import { ListingControls } from '@/components/storefront/listing-controls';
import { Pagination } from '@/components/storefront/pagination';
import { ProductCard } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ListingFilters, ProductListing, SortOption } from '@/types/storefront';
import { Head } from '@inertiajs/react';

interface SearchPageProps {
    query: string;
    listing: ProductListing;
    filters: ListingFilters;
    sortOptions: SortOption[];
}

export default function SearchPage({ query, listing, filters, sortOptions }: SearchPageProps) {
    return (
        <StorefrontLayout>
            <Head title={query ? `Search: ${query}` : 'Search'} />

            <h1 className="mb-6 text-2xl font-semibold">
                {query ? `Results for "${query}"` : 'Search'}
                {query !== '' && <span className="text-muted-foreground ml-2 text-base font-normal">({listing.total})</span>}
            </h1>

            {query === '' ? (
                <p className="text-muted-foreground">Type something in the search bar above to find products.</p>
            ) : (
                <>
                    <ListingControls filters={filters} sortOptions={sortOptions} total={listing.total} />

                    {listing.items.length === 0 ? (
                        <p className="text-muted-foreground">No products match "{query}".</p>
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
                </>
            )}
        </StorefrontLayout>
    );
}
