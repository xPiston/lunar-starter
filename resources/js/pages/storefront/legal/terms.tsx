import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import { Head } from '@inertiajs/react';

export default function TermsPage() {
    return (
        <StorefrontLayout>
            <Head title="Terms of Service" />

            <div className="mx-auto max-w-3xl">
                <Card className="mb-8 p-6">
                    <h1 className="mb-2 text-2xl font-semibold">Terms of Service</h1>
                    <p className="text-muted-foreground text-sm">Last updated: [DATE]</p>
                </Card>

                <div className="border-destructive/50 bg-destructive/10 text-destructive mb-8 rounded-lg border p-4 text-sm">
                    <strong>Template placeholder.</strong> This page is a starting structure, not legal advice or a ready-to-publish document. Replace
                    every <code className="font-mono">[bracketed]</code> value and have the final text reviewed by a lawyer for your jurisdiction and
                    business before going live.
                </div>

                <div className="space-y-8 text-sm leading-relaxed">
                    <section>
                        <h2 className="mb-2 text-lg font-medium">1. Who we are</h2>
                        <p>
                            [Company Legal Name], a [company type, e.g. limited company] registered in [jurisdiction] under number [registration
                            number], with its registered address at [company address] ("we", "us", "our"), operates this website. You can contact us
                            at [contact email].
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">2. Acceptance of these terms</h2>
                        <p>
                            By placing an order through this website, you agree to be bound by these Terms of Service and our{' '}
                            <a href={route('legal.privacy')} className="text-primary underline">
                                Privacy Policy
                            </a>
                            . If you do not agree, please do not use this website.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">3. Products and pricing</h2>
                        <p>
                            We make reasonable efforts to display product information and prices accurately. Prices are shown in [currency] and
                            [include/exclude] applicable taxes, calculated at checkout based on your delivery address. We reserve the right to correct
                            pricing errors and to change prices at any time before an order is placed.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">4. Orders and payment</h2>
                        <p>
                            Placing an order is an offer to buy, which we may accept or decline. Payment is processed securely by Stripe; we do not
                            store your full card details on our own servers. An order is confirmed once payment has been successfully captured and you
                            receive a confirmation email.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">5. Shipping and delivery</h2>
                        <p>
                            Estimated delivery times and shipping costs are shown at checkout. [Describe delivery areas, carriers, and typical
                            timeframes.] Risk in the goods passes to you on delivery.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">6. Returns and refunds</h2>
                        <p>
                            [Describe your return window (e.g. "within 14 days of delivery"), condition requirements, who pays return shipping, and
                            how refunds are issued.] This does not affect any statutory right of withdrawal or refund you may have under the law
                            applicable to you.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">7. Intellectual property</h2>
                        <p>
                            All content on this website — including text, graphics, logos, and product images — is owned by or licensed to us and may
                            not be reused without our written permission.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">8. Limitation of liability</h2>
                        <p>
                            To the fullest extent permitted by law, we are not liable for indirect or consequential losses arising from your use of
                            this website or your order. Nothing in these terms limits liability that cannot legally be limited or excluded.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">9. Governing law</h2>
                        <p>These terms are governed by the laws of [jurisdiction], without regard to conflict-of-law principles.</p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">10. Changes to these terms</h2>
                        <p>
                            We may update these terms from time to time. The version in force is the one published on this page at the time you place
                            an order.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">11. Contact</h2>
                        <p>Questions about these terms? Contact us at [contact email].</p>
                    </section>
                </div>
            </div>
        </StorefrontLayout>
    );
}
