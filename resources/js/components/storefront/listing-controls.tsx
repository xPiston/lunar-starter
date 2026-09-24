import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { ListingFilters, SortOption } from '@/types/storefront';
import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface ListingControlsProps {
    filters: ListingFilters;
    sortOptions: SortOption[];
    total: number;
}

/**
 * Sort and filter bar for a listing.
 *
 * Every change is a visit, not local state: the filtered listing has to be a
 * real URL - shareable, bookmarkable, and reachable with the back button.
 * `preserveState` keeps the text inputs from being remounted under the
 * visitor's cursor while the page reloads.
 */
export function ListingControls({ filters, sortOptions, total }: ListingControlsProps) {
    const [minPrice, setMinPrice] = useState(filters.min_price !== null ? String(filters.min_price / 100) : '');
    const [maxPrice, setMaxPrice] = useState(filters.max_price !== null ? String(filters.max_price / 100) : '');

    const hasFilters = filters.min_price !== null || filters.max_price !== null || filters.in_stock_only;

    function apply(changes: Record<string, string | number | boolean | null>) {
        // Page 1 on every change: staying on page 7 of a result set that just
        // shrank to two pages lands the visitor on an empty grid.
        router.get(window.location.pathname, cleaned({ ...currentParams(), page: null, ...changes }), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function submitPrices(event: FormEvent) {
        event.preventDefault();
        apply({ min_price: minPrice || null, max_price: maxPrice || null });
    }

    return (
        <div className="mb-6 flex flex-wrap items-center gap-4 border-b pb-6">
            <div className="flex items-center gap-2">
                <Label htmlFor="sort" className="text-muted-foreground text-sm">
                    Sort
                </Label>
                <Select value={filters.sort} onValueChange={(value) => apply({ sort: value })}>
                    <SelectTrigger id="sort" className="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {sortOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Label beside the fields, not above them: stacked, it pushed
                this group half a line off the baseline of the others. */}
            <form onSubmit={submitPrices} className="flex items-center gap-2">
                <Label htmlFor="min_price" className="text-muted-foreground text-sm">
                    Price
                </Label>
                <Input
                    id="min_price"
                    type="number"
                    min="0"
                    inputMode="decimal"
                    placeholder="Min"
                    value={minPrice}
                    onChange={(event) => setMinPrice(event.target.value)}
                    className="w-24"
                />
                <Input
                    type="number"
                    min="0"
                    inputMode="decimal"
                    placeholder="Max"
                    aria-label="Maximum price"
                    value={maxPrice}
                    onChange={(event) => setMaxPrice(event.target.value)}
                    className="w-24"
                />
                <Button type="submit" variant="secondary">
                    Apply
                </Button>
            </form>

            <Button
                type="button"
                variant={filters.in_stock_only ? 'default' : 'outline'}
                aria-pressed={filters.in_stock_only}
                onClick={() => apply({ in_stock: filters.in_stock_only ? null : true })}
            >
                In stock only
            </Button>

            <div className="text-muted-foreground ml-auto flex items-center gap-3 text-sm">
                <span>
                    {total} {total === 1 ? 'product' : 'products'}
                </span>
                {hasFilters && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setMinPrice('');
                            setMaxPrice('');
                            apply({ min_price: null, max_price: null, in_stock: null });
                        }}
                    >
                        <X className="size-4" />
                        Clear
                    </Button>
                )}
            </div>
        </div>
    );
}

function currentParams(): Record<string, string> {
    return Object.fromEntries(new URLSearchParams(window.location.search));
}

/**
 * Drops empty values so the URL carries only what was actually chosen -
 * otherwise every visit accumulates `min_price=&in_stock=` noise, and the
 * "is this filtered?" check behind `noindex` would see filters that aren't.
 */
function cleaned(params: Record<string, string | number | boolean | null>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(params)
            .filter(([, value]) => value !== null && value !== '' && value !== false)
            .map(([key, value]) => [key, String(value)]),
    );
}
