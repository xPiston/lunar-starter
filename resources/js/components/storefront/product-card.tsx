import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { ProductSummary } from '@/types/storefront';
import { Link, router } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';

export function ProductCard({ product }: { product: ProductSummary }) {
    function quickAdd() {
        router.post(route('cart.lines.store'), { product_variant_id: product.default_variant_id, quantity: 1 }, { preserveScroll: true });
    }

    return (
        <Card className="hover:border-foreground/20 overflow-hidden transition-colors">
            <Link href={route('products.show', product.slug)} className="block">
                <div className="bg-muted flex aspect-square items-center justify-center overflow-hidden">
                    {product.thumbnail_url ? (
                        <img src={product.thumbnail_url} alt={product.name} className="h-full w-full object-cover" />
                    ) : (
                        <span className="text-muted-foreground text-sm">No image</span>
                    )}
                </div>
                <div className="px-4 pt-4">
                    <p className="truncate font-medium">{product.name}</p>
                    <p className="text-muted-foreground mt-1 text-sm">From {product.price_from.formatted}</p>
                </div>
            </Link>

            <div className="p-4 pt-3">
                <Button size="sm" className="w-full" disabled={product.default_variant_id === 0} onClick={quickAdd}>
                    <ShoppingCart />
                    Add to Cart
                </Button>
            </div>
        </Card>
    );
}
