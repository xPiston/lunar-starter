import { RichText } from '@/components/storefront/rich-text';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ContentPage } from '@/types/storefront';
import { Head } from '@inertiajs/react';

export default function CustomPage({ page }: { page: ContentPage }) {
    return (
        <StorefrontLayout>
            <Head title={page.title} />

            <article className="mx-auto max-w-2xl">
                <h1 className="text-2xl font-semibold sm:text-4xl">{page.title}</h1>

                {page.image_url && <img src={page.image_url} alt="" className="bg-muted mt-6 aspect-[3/2] w-full rounded-2xl border object-cover" />}

                <RichText html={page.body} className="mt-8" />
            </article>
        </StorefrontLayout>
    );
}
