import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import StorefrontLayout from '@/layouts/storefront-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';

export default function GuestOrderLookupPage() {
    const { data, setData, post, processing, errors } = useForm({
        reference: '',
        email: '',
    });

    // `lookup` isn't a form field - it's the generic "no match" error from
    // GuestOrderLookupController::find(), only reachable via the shared
    // page errors bag.
    const pageErrors = (usePage().props.errors ?? {}) as Partial<Record<string, string>>;

    function submit(event: FormEvent) {
        event.preventDefault();
        post(route('orders.lookup.find'));
    }

    return (
        <StorefrontLayout>
            <Head title="Track your order" />

            <Card className="mx-auto max-w-sm p-8">
                <h1 className="mb-2 text-xl font-semibold">Track your order</h1>
                <p className="text-muted-foreground mb-6 text-sm">Enter your order reference and the email address you used at checkout.</p>

                {pageErrors.lookup && (
                    <div className="border-destructive/50 bg-destructive/10 text-destructive mb-6 rounded-md border px-4 py-3 text-sm">
                        {pageErrors.lookup}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="reference">Order reference</Label>
                        <input
                            id="reference"
                            type="text"
                            value={data.reference}
                            onChange={(event) => setData('reference', event.target.value)}
                            className="border-input mt-1 h-10 w-full rounded-md border px-3"
                            required
                        />
                        {errors.reference && <p className="text-destructive mt-1 text-sm">{errors.reference}</p>}
                    </div>

                    <div>
                        <Label htmlFor="email">Email</Label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            className="border-input mt-1 h-10 w-full rounded-md border px-3"
                            required
                        />
                        {errors.email && <p className="text-destructive mt-1 text-sm">{errors.email}</p>}
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        Find my order
                    </Button>
                </form>
            </Card>
        </StorefrontLayout>
    );
}
