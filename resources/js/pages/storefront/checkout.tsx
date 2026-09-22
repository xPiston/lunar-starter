import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { Address, Cart, CheckoutSummary, Country } from '@/types/storefront';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { Check, CreditCard, MapPin, ShoppingCart } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';

interface CheckoutPageProps {
    cart: Cart;
    checkout: CheckoutSummary;
}

// The index signature is required by useForm()'s `FormDataType` constraint
// (same for the billing/shipping wrapper type further down) - the "flat"
// interfaces in login.tsx/register.tsx don't have it, which leaves them with
// a pre-existing, out-of-scope tsc error (see README).
interface AddressForm {
    [key: string]: string | number;
    country_id: number | '';
    first_name: string;
    last_name: string;
    company_name: string;
    line_one: string;
    line_two: string;
    city: string;
    state: string;
    postcode: string;
    contact_email: string;
    contact_phone: string;
}

function emptyAddress(): AddressForm {
    return {
        country_id: '',
        first_name: '',
        last_name: '',
        company_name: '',
        line_one: '',
        line_two: '',
        city: '',
        state: '',
        postcode: '',
        contact_email: '',
        contact_phone: '',
    };
}

function fromDomain(address: Address | null): AddressForm {
    if (!address) {
        return emptyAddress();
    }

    return {
        country_id: address.country_id ?? '',
        first_name: address.first_name,
        last_name: address.last_name,
        company_name: address.company_name ?? '',
        line_one: address.line_one,
        line_two: address.line_two ?? '',
        city: address.city,
        state: address.state ?? '',
        postcode: address.postcode,
        contact_email: address.contact_email ?? '',
        contact_phone: address.contact_phone ?? '',
    };
}

function sameAddress(a: Address | null, b: Address | null): boolean {
    if (!a || !b) {
        return !a && !b;
    }

    return a.line_one === b.line_one && a.postcode === b.postcode && a.city === b.city;
}

interface AddressFieldsProps {
    prefix: string;
    values: AddressForm;
    countries: Country[];
    errors: Partial<Record<string, string>>;
    onChange: (field: keyof AddressForm, value: string | number) => void;
}

function AddressFields({ prefix, values, countries, errors, onChange }: AddressFieldsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div>
                <Label htmlFor={`${prefix}-first_name`}>First name</Label>
                <input
                    id={`${prefix}-first_name`}
                    value={values.first_name}
                    onChange={(e) => onChange('first_name', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
                {errors[`${prefix}.first_name`] && <p className="text-destructive mt-1 text-sm">{errors[`${prefix}.first_name`]}</p>}
            </div>

            <div>
                <Label htmlFor={`${prefix}-last_name`}>Last name</Label>
                <input
                    id={`${prefix}-last_name`}
                    value={values.last_name}
                    onChange={(e) => onChange('last_name', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
            </div>

            <div className="sm:col-span-2">
                <Label htmlFor={`${prefix}-line_one`}>Address</Label>
                <input
                    id={`${prefix}-line_one`}
                    value={values.line_one}
                    onChange={(e) => onChange('line_one', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
                {errors[`${prefix}.line_one`] && <p className="text-destructive mt-1 text-sm">{errors[`${prefix}.line_one`]}</p>}
            </div>

            <div className="sm:col-span-2">
                <Label htmlFor={`${prefix}-line_two`}>Apartment, suite, etc.</Label>
                <input
                    id={`${prefix}-line_two`}
                    value={values.line_two}
                    onChange={(e) => onChange('line_two', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
            </div>

            <div>
                <Label htmlFor={`${prefix}-city`}>City</Label>
                <input
                    id={`${prefix}-city`}
                    value={values.city}
                    onChange={(e) => onChange('city', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
                {errors[`${prefix}.city`] && <p className="text-destructive mt-1 text-sm">{errors[`${prefix}.city`]}</p>}
            </div>

            <div>
                <Label htmlFor={`${prefix}-postcode`}>Postal code</Label>
                <input
                    id={`${prefix}-postcode`}
                    value={values.postcode}
                    onChange={(e) => onChange('postcode', e.target.value)}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                />
                {errors[`${prefix}.postcode`] && <p className="text-destructive mt-1 text-sm">{errors[`${prefix}.postcode`]}</p>}
            </div>

            <div className="sm:col-span-2">
                <Label htmlFor={`${prefix}-country_id`}>Country</Label>
                <select
                    id={`${prefix}-country_id`}
                    value={values.country_id}
                    onChange={(e) => onChange('country_id', Number(e.target.value))}
                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                >
                    <option value="">Select...</option>
                    {countries.map((country) => (
                        <option key={country.id} value={country.id}>
                            {country.name}
                        </option>
                    ))}
                </select>
                {errors[`${prefix}.country_id`] && <p className="text-destructive mt-1 text-sm">{errors[`${prefix}.country_id`]}</p>}
            </div>
        </div>
    );
}

function PaymentStep({ clientSecret, publishableKey, billing }: { clientSecret: string; publishableKey: string; billing: AddressForm }) {
    const stripePromise = useMemo(() => loadStripe(publishableKey), [publishableKey]);

    return (
        <Elements stripe={stripePromise} options={{ clientSecret }}>
            <PaymentForm billing={billing} />
        </Elements>
    );
}

function PaymentForm({ billing }: { billing: AddressForm }) {
    const stripe = useStripe();
    const elements = useElements();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setProcessing(true);
        setError(null);

        const result = await stripe.confirmPayment({
            elements,
            // 'if_required': resolves in JS without navigation for cards (the
            // common case); only redirects for payment methods that actually
            // require it, in which case /checkout/return takes over.
            redirect: 'if_required',
            confirmParams: {
                return_url: route('checkout.return'),
                payment_method_data: {
                    billing_details: {
                        name: `${billing.first_name} ${billing.last_name}`.trim(),
                        email: billing.contact_email || undefined,
                        phone: billing.contact_phone || undefined,
                        address: {
                            line1: billing.line_one,
                            line2: billing.line_two || undefined,
                            city: billing.city,
                            state: billing.state || undefined,
                            postal_code: billing.postcode,
                        },
                    },
                },
            },
        });

        if (result.error) {
            setError(result.error.message ?? 'Payment failed.');
            setProcessing(false);
            return;
        }

        router.post(route('checkout.complete'), { payment_intent: result.paymentIntent.id });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <PaymentElement options={{ fields: { billingDetails: 'never' } }} />
            {error && <p className="text-destructive text-sm">{error}</p>}
            <Button type="submit" disabled={!stripe || processing} className="w-full">
                {processing ? 'Processing...' : 'Pay'}
            </Button>
        </form>
    );
}

interface StepProps {
    icon: typeof ShoppingCart;
    label: string;
    state: 'done' | 'current' | 'upcoming';
}

function Step({ icon: Icon, label, state }: StepProps) {
    return (
        <div className="flex flex-col items-center gap-2">
            <span
                className={`flex size-10 items-center justify-center rounded-full border ${
                    state === 'upcoming' ? 'border-border text-muted-foreground' : 'border-primary bg-primary text-primary-foreground'
                }`}
            >
                {state === 'done' ? <Check className="size-5" /> : <Icon className="size-5" />}
            </span>
            <span className={`text-sm ${state === 'upcoming' ? 'text-muted-foreground' : 'font-medium'}`}>{label}</span>
        </div>
    );
}

function CheckoutStepper({ checkout }: { checkout: CheckoutSummary }) {
    const addressDone = checkout.state.billing_address !== null && checkout.state.selected_shipping_option !== null;
    const paymentActive = checkout.payment_intent !== null;

    return (
        <Card className="mb-8 flex items-center justify-center gap-6 p-6 sm:gap-16">
            <Step icon={ShoppingCart} label="Cart" state="done" />
            <div className={`h-px w-10 sm:w-24 ${addressDone ? 'bg-primary' : 'bg-border'}`} />
            <Step icon={MapPin} label="Address" state={addressDone ? 'done' : 'current'} />
            <div className={`h-px w-10 sm:w-24 ${paymentActive ? 'bg-primary' : 'bg-border'}`} />
            <Step icon={CreditCard} label="Payment" state={paymentActive ? 'current' : 'upcoming'} />
        </Card>
    );
}

export default function CheckoutPage({ cart, checkout }: CheckoutPageProps) {
    const errors = (usePage().props.errors ?? {}) as Partial<Record<string, string>>;

    const [sameAsBilling, setSameAsBilling] = useState(
        sameAddress(checkout.state.billing_address, checkout.state.shipping_address) || !checkout.state.shipping_address,
    );

    const { data, setData, transform, post, processing } = useForm<{ [key: string]: AddressForm; billing: AddressForm; shipping: AddressForm }>({
        billing: fromDomain(checkout.state.billing_address),
        shipping: fromDomain(checkout.state.shipping_address ?? checkout.state.billing_address),
    });

    function submitAddress(event: FormEvent) {
        event.preventDefault();

        transform((formData) => ({
            billing: formData.billing,
            shipping: sameAsBilling ? formData.billing : formData.shipping,
        }));

        post(route('checkout.address'), { preserveScroll: true });
    }

    function selectShipping(identifier: string) {
        router.post(route('checkout.shipping-option'), { identifier }, { preserveScroll: true });
    }

    return (
        <StorefrontLayout>
            <Head title="Checkout" />

            <h1 className="mb-6 text-2xl font-semibold">Checkout</h1>

            <CheckoutStepper checkout={checkout} />

            <div className="grid gap-8 md:grid-cols-3">
                <div className="space-y-8 md:col-span-2">
                    <Card className="p-6">
                        <h2 className="mb-4 font-medium">Address</h2>
                        <form onSubmit={submitAddress} className="space-y-6">
                            <div>
                                <Label htmlFor="billing-contact_email">Email</Label>
                                <input
                                    id="billing-contact_email"
                                    type="email"
                                    value={data.billing.contact_email}
                                    onChange={(e) => setData('billing', { ...data.billing, contact_email: e.target.value })}
                                    className="border-input mt-1 h-10 w-full rounded-md border px-3"
                                />
                                <p className="text-muted-foreground mt-1 text-sm">Your order confirmation will be sent here.</p>
                                {errors['billing.contact_email'] && (
                                    <p className="text-destructive mt-1 text-sm">{errors['billing.contact_email']}</p>
                                )}
                            </div>

                            <AddressFields
                                prefix="billing"
                                values={data.billing}
                                countries={checkout.countries}
                                errors={errors}
                                onChange={(field, value) => setData('billing', { ...data.billing, [field]: value })}
                            />

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={sameAsBilling}
                                    onChange={(e) => setSameAsBilling(e.target.checked)}
                                    className="border-input size-4 rounded"
                                />
                                Ship to the same address
                            </label>

                            {!sameAsBilling && (
                                <AddressFields
                                    prefix="shipping"
                                    values={data.shipping}
                                    countries={checkout.countries}
                                    errors={errors}
                                    onChange={(field, value) => setData('shipping', { ...data.shipping, [field]: value })}
                                />
                            )}

                            <Button type="submit" disabled={processing}>
                                Save address
                            </Button>
                        </form>
                    </Card>

                    <Card className="p-6">
                        <h2 className="mb-4 font-medium">Choose a delivery speed</h2>
                        {!checkout.state.shipping_address ? (
                            <p className="text-muted-foreground text-sm">Save your address first.</p>
                        ) : (
                            <RadioGroup value={checkout.state.selected_shipping_option ?? undefined} onValueChange={selectShipping}>
                                {checkout.shipping_options.map((option) => (
                                    <label
                                        key={option.identifier}
                                        className={`flex cursor-pointer items-center justify-between rounded-md border p-3 text-sm ${
                                            checkout.state.selected_shipping_option === option.identifier
                                                ? 'border-primary bg-accent'
                                                : 'border-input'
                                        }`}
                                    >
                                        <span className="flex items-center gap-3">
                                            <RadioGroupItem value={option.identifier} />
                                            <span>
                                                <span className="font-medium">{option.name}</span>
                                                {option.description && (
                                                    <span className="text-muted-foreground block text-xs">{option.description}</span>
                                                )}
                                            </span>
                                        </span>
                                        <span className="font-medium">{option.price.formatted}</span>
                                    </label>
                                ))}
                            </RadioGroup>
                        )}
                    </Card>

                    <Card className="p-6">
                        <h2 className="mb-4 font-medium">Payment</h2>
                        {checkout.payment_intent ? (
                            <PaymentStep
                                clientSecret={checkout.payment_intent.client_secret}
                                publishableKey={checkout.payment_intent.publishable_key}
                                billing={data.billing}
                            />
                        ) : (
                            <p className="text-muted-foreground text-sm">Complete the address and shipping steps to pay.</p>
                        )}
                    </Card>
                </div>

                <Card className="h-fit space-y-2 p-6">
                    <h2 className="mb-2 font-semibold">Pricing Details</h2>
                    {cart.lines.map((line) => (
                        <div key={line.id} className="flex justify-between text-sm">
                            <span>
                                {line.name} x{line.quantity}
                            </span>
                            <span>{line.line_total.formatted}</span>
                        </div>
                    ))}
                    <div className="flex justify-between border-t pt-2 text-sm">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span>{cart.sub_total.formatted}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Shipping</span>
                        <span>{cart.shipping_total.formatted}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Tax</span>
                        <span>{cart.tax_total.formatted}</span>
                    </div>
                    <div className="flex justify-between border-t pt-2 font-semibold">
                        <span>Total</span>
                        <span>{cart.total.formatted}</span>
                    </div>
                    {cart.coupon_code && (
                        <div className="border-t pt-3 text-sm">
                            <Badge variant="secondary">Code {cart.coupon_code} applied</Badge>
                        </div>
                    )}
                </Card>
            </div>
        </StorefrontLayout>
    );
}
