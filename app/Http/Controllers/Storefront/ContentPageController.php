<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Content\ContentPageNotFoundException;
use App\Application\Content\ShowContentPage;
use App\Domain\Content\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Standalone custom pages - "About us", "Shipping & returns", whatever the
 * shop needs. Articles live under /news and are served by NewsController.
 */
final class ContentPageController extends Controller
{
    public function __invoke(ShowContentPage $showContentPage, string $slug): Response
    {
        try {
            $page = $showContentPage->handle($slug, ContentType::Page);
        } catch (ContentPageNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return Inertia::render('storefront/content-page', [
            'page' => $page->toArray(),
            'meta' => (new PageMeta(
                title: $page->title,
                description: $page->excerpt ?? PageMeta::excerpt($page->body),
                imageUrl: $page->imageUrl,
            ))->toArray(),
        ]);
    }
}
