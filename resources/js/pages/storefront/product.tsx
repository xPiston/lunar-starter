import { ProductCard } from '@/components/storefront/product-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Product, ProductSummary, ProductVariant } from '@/types/storefront';
import { Head, Link, useForm } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface ProductPageProps {
    product: Product;
    relatedProducts: ProductSummary[];
}

// Below this, show "Only N left" instead of nothing - high enough to create
// urgency, low enough to not fire on every normal restock level.
const LOW_STOCK_THRESHOLD = 5;

export default function ProductPage({ product, relatedProducts }: ProductPageProps) {
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
                            className="text-muted-foreground mt-4 text-sm leading-relaxed"
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
