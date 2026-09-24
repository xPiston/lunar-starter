import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/storefront';
import { Check } from 'lucide-react';

/**
 * Where an order has got to.
 *
 * The steps mirror `config/order_notifications.php` - the same map the emails
 * are keyed on, so what the page shows and what the customer was told cannot
 * drift apart. A status outside that path (cancelled, refunded) is shown as a
 * plain label instead: it did not reach "step 2 of 4", it left the path.
 */
const STEPS: { handles: string[]; label: string }[] = [
    { handles: ['awaiting-payment', 'payment-offline'], label: 'Ordered' },
    { handles: ['payment-received'], label: 'Paid' },
    { handles: ['dispatched'], label: 'Shipped' },
    { handles: ['delivered'], label: 'Delivered' },
];

export function OrderProgress({ status }: { status: OrderStatus }) {
    const current = STEPS.findIndex((step) => step.handles.includes(status.handle));

    if (current === -1) {
        return (
            <div className="mb-6 flex items-center gap-2">
                <span className="text-muted-foreground text-sm">Status</span>
                <Badge variant="secondary">{status.label}</Badge>
            </div>
        );
    }

    return (
        <div className="mb-8">
            <ol className="flex items-center">
                {STEPS.map((step, index) => {
                    const done = index <= current;

                    return (
                        <li key={step.label} className={cn('flex items-center', index < STEPS.length - 1 && 'flex-1')}>
                            <div className="flex flex-col items-center gap-1.5">
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-full border text-xs font-medium',
                                        done ? 'bg-primary text-primary-foreground border-transparent' : 'text-muted-foreground',
                                    )}
                                    aria-hidden="true"
                                >
                                    {done ? <Check className="size-4" /> : index + 1}
                                </span>
                                <span className={cn('text-xs', done ? 'font-medium' : 'text-muted-foreground')}>{step.label}</span>
                            </div>
                            {index < STEPS.length - 1 && (
                                <span className={cn('mx-2 mb-5 h-px flex-1', index < current ? 'bg-primary' : 'bg-border')} />
                            )}
                        </li>
                    );
                })}
            </ol>
            <p className="text-muted-foreground mt-3 text-center text-sm">{status.label}</p>
        </div>
    );
}
