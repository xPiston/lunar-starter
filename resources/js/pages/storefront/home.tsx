import { HeroSlider } from '@/components/storefront/hero-slider';
import { ProductCard } from '@/components/storefront/product-card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { CollectionSummary, HeroSlide, ProductSummary } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

interface HomeProps {
    slides: HeroSlide[];
    products: ProductSummary[];
    collections: CollectionSummary[];
}

export default function Home({ slides, products, collections }: HomeProps) {
    return (
        <StorefrontLayout>
            <Head title="Shop" />

            {/* The slider is editorial content managed in the back office. A
                shop that hasn't set any up still needs a homepage, so the
                original heading stays as the fallback. */}
            {slides.length > 0 ? (
                <HeroSlider slides={slides} />
            ) : (
                <section className="bg-secondary/30 mb-10 rounded-2xl border px-8 py-14 text-center sm:py-20">
                    <h1 className="text-3xl font-semibold sm:text-4xl">Everything you need, in one place</h1>
                    <p className="text-muted-foreground mx-auto mt-3 max-w-md text-sm sm:text-base">
                        Fresh arrivals, real stock, and a checkout that just works.
                    </p>
                </section>
            )}

            {collections.length > 0 && (
                <nav className="mb-10 flex flex-wrap gap-3">
                    {collections.map((collection) => (
                        <Link
                            key={collection.id}
                            href={route('collections.show', collection.slug)}
                            className="border-input bg-secondary/40 hover:bg-secondary rounded-full border px-4 py-1.5 text-sm"
                        >
                            {collection.name}
                        </Link>
                    ))}
                </nav>
            )}

            <h2 className="mb-6 text-xl font-semibold">New Arrivals</h2>

            {products.length === 0 ? (
                <p className="text-muted-foreground">No products published yet.</p>
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
