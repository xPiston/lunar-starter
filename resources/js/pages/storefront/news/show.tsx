import { formatDate, RichText } from '@/components/storefront/rich-text';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ContentPage } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function NewsArticle({ article }: { article: ContentPage }) {
    return (
        <StorefrontLayout>
            <Head title={article.title} />

            {/* Narrower than the shop grid on purpose: long-form text is
                unreadable at the full width of a product listing. */}
            <article className="mx-auto max-w-2xl">
                {/* flex, not inline-flex: as an inline box the date that
                    follows would run onto the same line as the link. */}
                <Link href={route('news.index')} className="text-muted-foreground hover:text-foreground mb-6 flex w-fit items-center gap-2 text-sm">
                    <ArrowLeft className="size-4" />
                    All news
                </Link>

                {article.published_at && (
                    <time dateTime={article.published_at} className="text-muted-foreground block text-xs">
                        {formatDate(article.published_at)}
                    </time>
                )}

                <h1 className="mt-1 text-2xl font-semibold sm:text-4xl">{article.title}</h1>

                {article.image_url && (
                    <img src={article.image_url} alt="" className="bg-muted mt-6 aspect-[3/2] w-full rounded-2xl border object-cover" />
                )}

                <RichText html={article.body} className="mt-8" />
            </article>
        </StorefrontLayout>
    );
}
