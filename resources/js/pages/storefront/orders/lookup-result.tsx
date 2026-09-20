import OrderDetail from '@/components/storefront/order-detail';
import { Button } from '@/components/ui/button';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Order } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

interface GuestOrderLookupResultPageProps {
    order: Order;
}

export default function GuestOrderLookupResultPage({ order }: GuestOrderLookupResultPageProps) {
    return (
        <StorefrontLayout>
            <Head title={`Order ${order.reference}`} />

            <Link href={route('orders.lookup')} className="text-muted-foreground hover:text-foreground mb-4 inline-block text-sm underline">
                &larr; Look up another order
            </Link>

            <h1 className="mb-6 text-2xl font-semibold">Order {order.reference}</h1>

            <OrderDetail order={order} />

            <div className="mt-8">
                <Link href={route('home')}>
                    <Button variant="outline">Continue shopping</Button>
                </Link>
            </div>
        </StorefrontLayout>
    );
}
