import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { OrderSummary } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

interface OrdersIndexPageProps {
    orders: OrderSummary[];
}

export default function OrdersIndexPage({ orders }: OrdersIndexPageProps) {
    return (
        <StorefrontLayout>
            <Head title="My orders" />

            <h1 className="mb-6 text-2xl font-semibold">My orders</h1>

            {orders.length === 0 ? (
                <div className="text-muted-foreground">
                    You haven&apos;t placed any orders yet.{' '}
                    <Link href={route('home')} className="text-primary underline">
                        Start shopping
                    </Link>
                </div>
            ) : (
                <Card className="divide-y overflow-hidden p-0">
                    {orders.map((order) => (
                        <Link
                            key={order.reference}
                            href={route('account.orders.show', order.reference)}
                            className="hover:bg-accent flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p className="font-medium">{order.reference}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {order.placed_at} &middot; {order.item_count} item{order.item_count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <div className="flex items-center gap-4">
                                <Badge variant="secondary">{order.status}</Badge>
                                <p className="font-medium">{order.total.formatted}</p>
                            </div>
                        </Link>
                    ))}
                </Card>
            )}
        </StorefrontLayout>
    );
}
