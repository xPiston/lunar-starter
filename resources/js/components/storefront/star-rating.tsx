import { cn } from '@/lib/utils';
import { Star } from 'lucide-react';

interface StarRatingProps {
    /** 0 to 5, fractions allowed - a 4.3 average fills four and a bit. */
    value: number;
    size?: 'sm' | 'md';
    className?: string;
}

/**
 * Five stars, filled proportionally.
 *
 * The overlay is clipped by width rather than rounded to the nearest star:
 * rounding 4.4 up to 4.5 is a small lie about what customers actually said.
 * The value is announced once, as text, so a screen reader hears "4.3 out of
 * 5" instead of five identical icons.
 */
export function StarRating({ value, size = 'md', className }: StarRatingProps) {
    const clamped = Math.max(0, Math.min(5, value));
    const starSize = size === 'sm' ? 'size-3.5' : 'size-4';

    return (
        <span className={cn('inline-flex items-center', className)} role="img" aria-label={`${clamped.toFixed(1)} out of 5`}>
            <span className="relative inline-flex">
                <span className="text-muted-foreground/40 inline-flex">
                    {[0, 1, 2, 3, 4].map((index) => (
                        <Star key={index} className={starSize} aria-hidden="true" />
                    ))}
                </span>
                <span className="absolute inset-0 inline-flex overflow-hidden text-amber-500" style={{ width: `${(clamped / 5) * 100}%` }}>
                    {[0, 1, 2, 3, 4].map((index) => (
                        <Star key={index} className={cn(starSize, 'shrink-0 fill-current')} aria-hidden="true" />
                    ))}
                </span>
            </span>
        </span>
    );
}
