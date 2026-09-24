import { OrderProgress } from '@/components/storefront/order-progress';
import { Card } from '@/components/ui/card';
import type { Order } from '@/types/storefront';

interface OrderDetailProps {
    order: Order;
}

// Shared between the logged-in "my orders" detail page and the guest
// lookup result page - both render the exact same Order shape, just reached
// through a different identity check server-side.
export default function OrderDetail({ order }: OrderDetailProps) {
    return (
        <>
            <OrderProgress status={order.status} />

            <div className="grid gap-8 md:grid-cols-3">
                <Card className="space-y-2 p-6 md:col-span-2">
                    {order.lines.map((line) => (
                        <div key={line.id} className="flex justify-between text-sm">
                            <span>
                                {line.name} x{line.quantity}
                            </span>
                            <span>{line.line_total.formatted}</span>
                        </div>
                    ))}
                    <div className="flex justify-between border-t pt-2 text-sm">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span>{order.sub_total.formatted}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Shipping</span>
                        <span>{order.shipping_total.formatted}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Tax</span>
                        <span>{order.tax_total.formatted}</span>
                    </div>
                    <div className="flex justify-between border-t pt-2 font-semibold">
                        <span>Total</span>
                        <span>{order.total.formatted}</span>
                    </div>
                </Card>

                {order.shipping_address && (
                    <Card className="h-fit p-6 text-sm">
                        <h2 className="mb-2 font-medium">Shipped to</h2>
                        <p>
                            {order.shipping_address.first_name} {order.shipping_address.last_name}
                        </p>
                        <p>{order.shipping_address.line_one}</p>
                        {order.shipping_address.line_two && <p>{order.shipping_address.line_two}</p>}
                        <p>
                            {order.shipping_address.city}, {order.shipping_address.postcode}
                        </p>
                    </Card>
                )}
            </div>
        </>
    );
}
