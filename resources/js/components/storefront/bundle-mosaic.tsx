import { cn } from '@/lib/utils';
import type { BundleItem } from '@/types/storefront';

/**
 * A bundle's picture, made of its contents.
 *
 * Most shops photograph a bundle as one shot; a template cannot assume that
 * picture exists, and one part's photo standing in for the whole box is
 * misleading. Laying every part out says what is being sold. When the shop
 * does upload its own image, that one wins - this is the fallback.
 *
 * Two columns from two parts up, which keeps every tile square-ish whether
 * the bundle holds two items or six.
 */
export function BundleMosaic({ items, className }: { items: BundleItem[]; className?: string }) {
    const withImages = items.filter((item) => item.image_url !== null);

    if (withImages.length === 0) {
        return <div className={cn('bg-muted', className)} />;
    }

    return (
        <div className={cn('bg-muted grid gap-px', withImages.length > 1 && 'grid-cols-2', className)}>
            {withImages.map((item) => (
                <div key={item.name} className="bg-background flex items-center justify-center overflow-hidden">
                    <img src={item.image_url ?? ''} alt="" className="h-full w-full object-cover" />
                </div>
            ))}
        </div>
    );
}
