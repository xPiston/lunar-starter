import { BundleCard } from '@/components/storefront/bundle-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Bundle } from '@/types/storefront';
import { Head } from '@inertiajs/react';

export default function BundlesIndex({ bundles }: { bundles: Bundle[] }) {
    return (
        <StorefrontLayout>
            <Head title="Bundles" />

            <header className="mb-8">
                <h1 className="text-2xl font-semibold sm:text-3xl">Bundles</h1>
                <p className="text-muted-foreground mt-2 text-sm">Sets of our products, sold together for less than buying them one by one.</p>
            </header>

            {bundles.length === 0 ? (
                <p className="text-muted-foreground">No bundles on offer right now.</p>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {bundles.map((bundle) => (
                        <BundleCard key={bundle.id} bundle={bundle} />
                    ))}
                </div>
            )}
        </StorefrontLayout>
    );
}
