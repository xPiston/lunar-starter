import { ProductCard } from '@/components/storefront/product-card';
import { ProductReviews } from '@/components/storefront/product-reviews';
import { StarRating } from '@/components/storefront/star-rating';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Product, ProductSummary, ProductVariant, RatingSummary, Review } from '@/types/storefront';
import { Head, Link, useForm } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface ProductPageProps {
    product: Product;
    relatedProducts: ProductSummary[];
    rating: RatingSummary;
    reviews: Review[];
    canReview: boolean;
}

// Below this, show "Only N left" instead of nothing - high enough to create
// urgency, low enough to not fire on every normal restock level.
const LOW_STOCK_THRESHOLD = 5;

export default function ProductPage({ product, relatedProducts, rating, reviews, canReview }: ProductPageProps) {
    const [selectedVariant, setSelectedVariant] = useState<ProductVariant>(product.variants[0]);
    const gallery = product.images.length > 0 ? product.images : product.thumbnail_url ? [product.thumbnail_url] : [];
    const [activeImage, setActiveImage] = useState(gallery[0]);

    const { data, setData, post, processing, errors } = useForm({
        product_variant_id: selectedVariant?.id ?? 0,
        quantity: 1,
    });

    const isOutOfStock = selectedVariant?.available_stock === 0;
    const maxQuantity = selectedVariant?.available_stock ?? undefined;

    function selectVariant(variant: ProductVariant) {
        setSelectedVariant(variant);
        setData('product_variant_id', variant.id);
    }

    function changeQuantity(delta: number) {
        const next = data.quantity + delta;
        if (next < 1 || (maxQuantity !== undefined && next > maxQuantity)) {
            return;
        }
        setData('quantity', next);
    }

    function addToCart(event: FormEvent) {
        event.preventDefault();
        post(route('cart.lines.store'), { preserveScroll: true });
    }

    return (
        <StorefrontLayout>
            <Head title={product.name} />

            <div className="grid gap-10 md:grid-cols-2">
                <div>
                    <div className="bg-muted flex aspect-square items-center justify-center overflow-hidden rounded-xl">
                        {activeImage ? (
                            <img src={activeImage} alt={product.name} className="h-full w-full object-cover" />
                        ) : (
                            <span className="text-muted-foreground text-sm">No image</span>
                        )}
                    </div>

                    {gallery.length > 1 && (
                        <div className="mt-4 flex gap-3">
                            {gallery.map((image) => (
                                <button
                                    key={image}
                                    type="button"
                                    onClick={() => setActiveImage(image)}
                                    className={`bg-muted size-16 shrink-0 overflow-hidden rounded-lg border-2 ${
                                        image === activeImage ? 'border-primary' : 'border-transparent'
                                    }`}
                                >
                                    <img src={image} alt="" className="h-full w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h1 className="text-2xl font-semibold">{product.name}</h1>

                    {rating.count > 0 && (
                        <a href="#reviews" className="mt-1 inline-flex items-center gap-2 text-sm">
                            <StarRating value={rating.average} size="sm" />
                            <span className="text-muted-foreground underline-offset-4 hover:underline">
                                {rating.average.toFixed(1)} ({rating.count})
                            </span>
                        </a>
                    )}

                    <p className="mt-2 text-2xl font-semibold">{selectedVariant?.price.formatted}</p>

                    {isOutOfStock ? (
                        <Badge variant="destructive" className="mt-2">
                            Out of stock
                        </Badge>
                    ) : (
                        selectedVariant?.available_stock !== null &&
                        selectedVariant.available_stock <= LOW_STOCK_THRESHOLD && (
                            <Badge className="mt-2 border-amber-500/30 bg-amber-500/15 text-amber-600 dark:text-amber-500">
                                Only {selectedVariant.available_stock} left in stock
                            </Badge>
                        )
                    )}

                    {product.description && (
                        <div
                            // Descriptions are admin-authored HTML, so the list styles Tailwind's
                            // preflight resets have to be put back here - otherwise every <ul>
                            // renders as unmarked lines.
                            className="text-muted-foreground mt-4 space-y-3 text-sm leading-relaxed [&_li]:mt-1 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5"
                            dangerouslySetInnerHTML={{ __html: product.description }}
                        />
                    )}

                    {product.variants.length > 1 && (
                        <div className="mt-6">
                            <Label>Variant</Label>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {product.variants.map((variant) => (
                                    <button
                                        key={variant.id}
                                        type="button"
                                        onClick={() => selectVariant(variant)}
                                        className={`rounded-full border px-4 py-1.5 text-sm transition-colors ${
                                            variant.id === selectedVariant?.id
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input hover:bg-accent'
                                        }`}
                                    >
                                        {variant.option_summary || variant.sku}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <form onSubmit={addToCart} className="mt-6">
                        <Label>Quantity</Label>
                        <div className="mt-2 flex items-center gap-4">
                            <div className="border-input inline-flex items-center rounded-full border">
                                <button
                                    type="button"
                                    onClick={() => changeQuantity(-1)}
                                    disabled={isOutOfStock || data.quantity <= 1}
                                    className="flex size-10 items-center justify-center disabled:opacity-40"
                                    aria-label="Decrease quantity"
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="w-8 text-center text-sm font-medium">{data.quantity}</span>
                                <button
                                    type="button"
                                    onClick={() => changeQuantity(1)}
                                    disabled={isOutOfStock || (maxQuantity !== undefined && data.quantity >= maxQuantity)}
                                    className="flex size-10 items-center justify-center disabled:opacity-40"
                                    aria-label="Increase quantity"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>

                            <Button type="submit" size="lg" className="flex-1" disabled={processing || !selectedVariant || isOutOfStock}>
                                <ShoppingCart />
                                {isOutOfStock ? 'Out of stock' : 'Add to cart'}
                            </Button>
                        </div>
                        {errors.quantity && <p className="text-destructive mt-2 text-sm">{errors.quantity}</p>}
                    </form>
                </div>
            </div>

            <ProductReviews productSlug={product.slug} rating={rating} reviews={reviews} canReview={canReview} />

            {relatedProducts.length > 0 && (
                <section className="mt-16">
                    <h2 className="mb-6 text-xl font-semibold">You may also like</h2>
                    <div className="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                        {relatedProducts.map((related) => (
                            <ProductCard key={related.id} product={related} />
                        ))}
                    </div>
                </section>
            )}

            <div className="mt-8">
                <Link href={route('home')} className="text-muted-foreground hover:text-foreground text-sm underline">
                    &larr; Back to shop
                </Link>
            </div>
        </StorefrontLayout>
    );
}
