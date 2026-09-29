import { cn } from '@/lib/utils';
import type { PriceTier } from '@/types/storefront';

interface PackPickerProps {
    tiers: PriceTier[];
    /** The quantity currently chosen, which is a tier's quantity. */
    value: number;
    onSelect: (quantity: number) => void;
    /** What one unit is called here: "box", "pack", "bottle"… */
    unitLabel?: string;
}

/**
 * "How many?" as a row of packs rather than a number field.
 *
 * Each card shows what the pack costs, what it would have cost at the
 * single-unit price, and the resulting per-unit figure - the three numbers
 * someone actually compares. They come from the server already formatted;
 * doing that arithmetic here would mean rounding money in JavaScript.
 *
 * Radio inputs rather than clickable divs: this is one choice among several,
 * so it should be reachable by keyboard and announced as a group.
 */
export function PackPicker({ tiers, value, onSelect, unitLabel = 'unit' }: PackPickerProps) {
    return (
        <fieldset>
            <legend className="mb-2 text-sm font-medium">How many?</legend>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {tiers.map((tier) => {
                    const selected = tier.quantity === value;

                    return (
                        <label
                            key={tier.quantity}
                            className={cn(
                                'relative cursor-pointer rounded-xl border p-4 text-center transition-colors',
                                selected ? 'border-foreground ring-foreground/20 ring-2' : 'hover:border-foreground/30',
                            )}
                        >
                            <input
                                type="radio"
                                name="pack"
                                value={tier.quantity}
                                checked={selected}
                                onChange={() => onSelect(tier.quantity)}
                                className="sr-only"
                            />

                            {tier.percent_off > 0 && (
                                <span className="bg-primary text-primary-foreground absolute -top-2.5 left-1/2 -translate-x-1/2 rounded-full px-2 py-0.5 text-xs font-medium">
                                    −{tier.percent_off}%
                                </span>
                            )}

                            <span className="block text-sm font-medium">
                                {tier.quantity} {unitLabel}
                                {tier.quantity > 1 ? 's' : ''}
                            </span>

                            <span className="mt-1 block">
                                <span className="font-semibold">{tier.total.formatted}</span>
                                {tier.percent_off > 0 && (
                                    <span className="text-muted-foreground ml-1.5 text-sm line-through">{tier.undiscounted_total.formatted}</span>
                                )}
                            </span>

                            <span className="text-muted-foreground mt-1 block text-xs">
                                {tier.unit_price.formatted} / {unitLabel}
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
