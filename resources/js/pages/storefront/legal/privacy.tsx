import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import { Head } from '@inertiajs/react';

export default function PrivacyPage() {
    return (
        <StorefrontLayout>
            <Head title="Privacy Policy" />

            <div className="mx-auto max-w-3xl">
                <Card className="mb-8 p-6">
                    <h1 className="mb-2 text-2xl font-semibold">Privacy Policy</h1>
                    <p className="text-muted-foreground text-sm">Last updated: [DATE]</p>
                </Card>

                <div className="border-destructive/50 bg-destructive/10 text-destructive mb-8 rounded-lg border p-4 text-sm">
                    <strong>Template placeholder.</strong> This page describes what this template actually does with data by default, as a starting
                    point - it is not legal advice. Replace every <code className="font-mono">[bracketed]</code> value, adjust the sections to match
                    what your store really collects (analytics, marketing, etc.), and have the final text reviewed by a lawyer for your jurisdiction
                    before going live.
                </div>

                <div className="space-y-8 text-sm leading-relaxed">
                    <section>
                        <h2 className="mb-2 text-lg font-medium">1. Who we are</h2>
                        <p>
                            [Company Legal Name] ("we", "us", "our") operates this website. For any privacy question or request, contact us at
                            [contact email].
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">2. Data we collect</h2>
                        <p>When you browse this site or place an order, we may collect:</p>
                        <ul className="mt-2 ml-5 list-disc space-y-1">
                            <li>Contact details you provide at checkout: name, email address, phone number.</li>
                            <li>Billing and shipping addresses.</li>
                            <li>Order history: products purchased, quantities, amounts.</li>
                            <li>
                                Payment information: handled entirely by Stripe, our payment processor. We never see or store your full card number.
                            </li>
                            <li>Basic technical data (IP address) used for cart/session handling and fraud/abuse prevention.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">3. How we use this data</h2>
                        <p>We use the data above to:</p>
                        <ul className="mt-2 ml-5 list-disc space-y-1">
                            <li>Process and fulfil your order, and send you an order confirmation email.</li>
                            <li>Provide customer support if you contact us about an order.</li>
                            <li>Prevent fraud and abuse (for example, rate-limiting checkout attempts).</li>
                            <li>Comply with legal and tax obligations (e.g. keeping order records).</li>
                        </ul>
                        <p className="mt-2">
                            [Add a section here if you also use this data for marketing emails, analytics, or advertising - and how customers can opt
                            out.]
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">4. Who we share it with</h2>
                        <p>We share data with the third parties strictly needed to run the store:</p>
                        <ul className="mt-2 ml-5 list-disc space-y-1">
                            <li>
                                <strong>Stripe</strong> — payment processing. See{' '}
                                <a href="https://stripe.com/privacy" className="text-primary underline" target="_blank" rel="noreferrer">
                                    Stripe's privacy policy
                                </a>
                                .
                            </li>
                            <li>[Shipping carrier(s)] — to deliver your order.</li>
                            <li>[Email/hosting provider] — to send transactional emails and host this site.</li>
                        </ul>
                        <p className="mt-2">We do not sell your personal data.</p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">5. Cookies</h2>
                        <p>
                            By default, this site only sets strictly necessary cookies: a session cookie (to keep your cart working) and a CSRF
                            security token. These don't require consent under most cookie laws, since the site can't function without them. [If you
                            add analytics, marketing pixels, or A/B testing tools, list them here and add a consent mechanism before enabling them -
                            this template doesn't include any by default.]
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">6. Data retention</h2>
                        <p>
                            We keep order records for [retention period, e.g. "the period required by our tax and accounting obligations"]. Account
                            data, if you create an account, is kept until you ask us to delete it, subject to our legal obligations.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">7. Your rights</h2>
                        <p>
                            Depending on where you live, you may have the right to access, correct, delete, or export your personal data, and to
                            object to certain uses of it. To exercise any of these rights, contact us at [contact email].
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">8. Security</h2>
                        <p>
                            We use industry-standard measures (encrypted connections, a PCI-compliant payment processor) to protect your data, but no
                            method of transmission or storage is 100% secure.
                        </p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">9. Changes to this policy</h2>
                        <p>We may update this policy from time to time. The version published on this page is the one in effect.</p>
                    </section>

                    <section>
                        <h2 className="mb-2 text-lg font-medium">10. Contact</h2>
                        <p>Questions about this policy or your data? Contact us at [contact email].</p>
                    </section>
                </div>
            </div>
        </StorefrontLayout>
    );
}
