<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Content\ContentPageNotFoundException;
use App\Application\Content\ListPublishedContent;
use App\Application\Content\ShowContentPage;
use App\Domain\Content\ContentPage;
use App\Domain\Content\ContentPageSummary;
use App\Domain\Content\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NewsController extends Controller
{
    public function index(ListPublishedContent $listContent): Response
    {
        return Inertia::render('storefront/news/index', [
            'articles' => array_map(
                static fn (ContentPageSummary $article): array => $article->toArray(),
                $listContent->handle(ContentType::Post),
            ),
            'meta' => (new PageMeta(
                title: 'News',
                description: 'Updates, stories and announcements from '.config('app.name').'.',
            ))->toArray(),
        ]);
    }

    public function show(ShowContentPage $showContentPage, string $slug): Response
    {
        try {
            $article = $showContentPage->handle($slug, ContentType::Post);
        } catch (ContentPageNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return Inertia::render('storefront/news/show', [
            'article' => $article->toArray(),
            'meta' => $this->meta($article)->toArray(),
        ]);
    }

    private function meta(ContentPage $article): PageMeta
    {
        $url = route('news.show', $article->slug);

        return new PageMeta(
            title: $article->title,
            description: $article->excerpt ?? PageMeta::excerpt($article->body),
            imageUrl: $article->imageUrl,
            // 'article' rather than the default 'website': it's what makes a
            // share card show a byline and date instead of a bare site name.
            type: 'article',
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $article->title,
                'description' => $article->excerpt ?? PageMeta::excerpt($article->body, 300),
                'image' => $article->imageUrl === null ? [] : [$article->imageUrl],
                'datePublished' => $article->publishedAt,
                'mainEntityOfPage' => $url,
                'url' => $url,
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                ],
            ],
            ogProperties: array_filter([
                'article:published_time' => $article->publishedAt,
            ]),
        );
    }
}
