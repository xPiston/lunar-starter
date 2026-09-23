import { formatDate } from '@/components/storefront/rich-text';
import { Card } from '@/components/ui/card';
import StorefrontLayout from '@/layouts/storefront-layout';
import type { ContentPageSummary } from '@/types/storefront';
import { Head, Link } from '@inertiajs/react';

export default function NewsIndex({ articles }: { articles: ContentPageSummary[] }) {
    return (
        <StorefrontLayout>
            <Head title="News" />

            <header className="mb-8">
                <h1 className="text-2xl font-semibold sm:text-3xl">News</h1>
                <p className="text-muted-foreground mt-2 text-sm">Updates, stories and announcements.</p>
            </header>

            {articles.length === 0 ? (
                <p className="text-muted-foreground">No articles published yet.</p>
            ) : (
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {articles.map((article) => (
                        <Card key={article.id} className="hover:border-foreground/20 overflow-hidden py-0 transition-colors">
                            <Link href={route('news.show', article.slug)} className="block">
                                {article.image_url && (
                                    <div className="bg-muted aspect-[3/2] overflow-hidden">
                                        <img src={article.image_url} alt="" className="h-full w-full object-cover" />
                                    </div>
                                )}
                                <div className="p-5">
                                    {article.published_at && (
                                        <time dateTime={article.published_at} className="text-muted-foreground text-xs">
                                            {formatDate(article.published_at)}
                                        </time>
                                    )}
                                    <h2 className="mt-1 font-medium">{article.title}</h2>
                                    {article.excerpt && <p className="text-muted-foreground mt-2 text-sm">{article.excerpt}</p>}
                                </div>
                            </Link>
                        </Card>
                    ))}
                </div>
            )}
        </StorefrontLayout>
    );
}
