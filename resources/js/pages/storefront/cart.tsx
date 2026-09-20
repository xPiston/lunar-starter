import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Cart, CartLine } from '@/types/storefront';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { type FormEvent } from 'react';

interface CartPageProps {
    cart: Cart;
}

function updateQuantity(line: CartLine, quantity: number) {
    if (quantity < 1) {
        return;
    }
    router.patch(route('cart.lines.update', line.id), { quantity }, { preserveScroll: true });
}

function removeLine(line: CartLine) {
    router.delete(route('cart.lines.destroy', line.id), { preserveScroll: true });
}

function removeCoupon() {
    router.delete(route('cart.coupon.destroy'), { preserveScroll: true });
}

function CouponForm() {
    const { data, setData, post, processing, reset } = useForm({ code: '' });
    // `coupon` isn't a field on this form - it's CartController::applyCoupon()'s
    // business-rule error (invalid/inapplicable code), only reachable via the
    // shared page errors bag, same as the cart-wide `quantity` error above.
    const pageErrors = (usePage().props.errors ?? {}) as Partial<Record<string, string>>;

    function submit(event: FormEvent) {
        event.preventDefault();
        post(route('cart.coupon.store'), { preserveScroll: true, onSuccess: () => reset() });
    }

    return (
        <form onSubmit={submit} className="space-y-1">
            <div className="flex gap-2">
                <input
                    type="text"
                    value={data.code}
                    onChange={(event) => setData('code', event.target.value)}
                    placeholder="Coupon code"
                    className="border-input bg-secondary/30 h-9 w-full rounded-md border px-3 text-sm uppercase placeholder:normal-case"
                />
                <Button type="submit" variant="outline" size="sm" disabled={processing || data.code === ''}>
                    Apply
                </Button>
            </div>
            {pageErrors.coupon && <p className="text-destructive text-sm">{pageErrors.coupon}</p>}
        </form>
    );
}

function CartLineRow({ line }: { line: CartLine }) {
    return (
        <Card className="flex items-center gap-4 p-4">
            <div className="bg-muted flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg">
                {line.thumbnail_url && <img src={line.thumbnail_url} alt={line.name} className="h-full w-full object-cover" />}
            </div>

            <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{line.name}</p>
                <p className="text-muted-foreground text-sm">{line.unit_price.formatted} / unit</p>

                <div className="mt-3 flex items-center gap-3">
                    <div className="border-input inline-flex items-center rounded-full border">
                        <button
                            type="button"
                            onClick={() => updateQuantity(line, line.quantity - 1)}
                            className="flex size-8 items-center justify-center"
                            aria-label="Decrease quantity"
                        >
                            <Minus className="size-3.5" />
                        </button>
                        <span className="w-6 text-center text-sm font-medium">{line.quantity}</span>
                        <button
                            type="button"
                            onClick={() => updateQuantity(line, line.quantity + 1)}
                            className="flex size-8 items-center justify-center"
                            aria-label="Increase quantity"
                        >
                            <Plus className="size-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={() => removeLine(line)}
                        aria-label="Remove item"
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
            </div>

            <p className="font-medium">{line.line_total.formatted}</p>
        </Card>
    );
}

export default function CartPage({ cart }: CartPageProps) {
    // Not tied to a specific line: Lunar's stock/quantity validators reject
    // the whole update, so there's no per-line error to attach this to
    // beyond re-fetching the cart (already done by the redirect back).
    const errors = (usePage().props.errors ?? {}) as Partial<Record<string, string>>;

    return (
        <StorefrontLayout>
            <Head title="Cart" />

            <h1 className="mb-6 text-2xl font-semibold">Your cart</h1>

            {errors.quantity && (
                <div className="border-destructive/50 bg-destructive/10 text-destructive mb-6 rounded-md border px-4 py-3 text-sm">
                    {errors.quantity}
                </div>
            )}

            {cart.lines.length === 0 ? (
                <div className="text-muted-foreground">
                    Your cart is empty.{' '}
                    <Link href={route('home')} className="text-primary underline">
                        Continue shopping
                    </Link>
                </div>
            ) : (
                <div className="grid gap-8 md:grid-cols-3">
                    <div className="space-y-4 md:col-span-2">
                        {cart.lines.map((line) => (
                            <CartLineRow key={line.id} line={line} />
                        ))}
                    </div>

                    <Card className="h-fit space-y-2 p-6">
                        <h2 className="mb-2 font-semibold">Pricing Details</h2>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Subtotal</span>
                            <span>{cart.sub_total.formatted}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Discounts</span>
                            <span>-{cart.discount_total.formatted}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Tax</span>
                            <span>{cart.tax_total.formatted}</span>
                        </div>
                        <div className="flex justify-between border-t pt-2 font-semibold">
                            <span>Total</span>
                            <span>{cart.total.formatted}</span>
                        </div>

                        <div className="border-t pt-3">
                            {cart.coupon_code ? (
                                <div className="flex items-center justify-between text-sm">
                                    <span>
                                        Code <span className="font-medium">{cart.coupon_code}</span> applied
                                    </span>
                                    <button type="button" onClick={removeCoupon} className="text-muted-foreground hover:text-foreground underline">
                                        Remove
                                    </button>
                                </div>
                            ) : (
                                <CouponForm />
                            )}
                        </div>

                        <Link href={route('checkout.show')} className="block">
                            <Button className="mt-4 w-full" size="lg">
                                Checkout
                            </Button>
                        </Link>
                    </Card>
                </div>
            )}
        </StorefrontLayout>
    );
}
