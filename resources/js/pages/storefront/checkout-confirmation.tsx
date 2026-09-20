import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Order } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

interface CheckoutConfirmationPageProps {
    order: Order;
}

export default function CheckoutConfirmationPage({ order }: CheckoutConfirmationPageProps) {
    return (
        <StorefrontLayout>
            <Head title="Order confirmed" />

            <div className="mx-auto max-w-xl text-center">
                <span className="bg-primary text-primary-foreground mx-auto flex size-14 items-center justify-center rounded-full">
                    <CheckCircle2 className="size-7" />
                </span>
                <h1 className="mt-4 text-2xl font-semibold">Thank you for your order!</h1>
                <p className="text-muted-foreground mt-2">
                    Order reference: <span className="text-foreground font-medium">{order.reference}</span>
                </p>
            </div>

            <Card className="mx-auto mt-8 max-w-xl space-y-2 p-6">
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

            <div className="mt-8 text-center">
                <Link href={route('home')}>
                    <Button variant="outline">Continue shopping</Button>
                </Link>
            </div>
        </StorefrontLayout>
    );
}
