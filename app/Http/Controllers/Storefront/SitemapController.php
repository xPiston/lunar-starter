<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Catalog\ListCollections;
use App\Application\Catalog\ListPublishedProductSlugs;
use App\Application\Content\ListPublishedContent;
use App\Domain\Catalog\CollectionSummary;
use App\Domain\Content\ContentPageSummary;
use App\Domain\Content\ContentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Serves /sitemap.xml and /robots.txt.
 *
 * Both are generated rather than dropped in public/: the sitemap has to
 * follow the catalogue, and robots.txt has to name the sitemap with an
 * absolute URL, which is only known at runtime (APP_URL differs per
 * environment). A static file in public/ would also be served by the web
 * server before ever reaching PHP.
 *
 * No caching layer here on purpose - it's two cheap queries. A catalogue
 * large enough to feel it should move to chunked sitemap index files, which
 * is a different design, not a cache in front of this one.
 */
final class SitemapController extends Controller
{
    public function sitemap(
        ListPublishedProductSlugs $listSlugs,
        ListCollections $listCollections,
        ListPublishedContent $listContent,
    ): Response {
        $urls = [route('home'), route('legal.terms'), route('legal.privacy'), route('orders.lookup')];

        foreach ($listCollections->handle() as $collection) {
            /** @var CollectionSummary $collection */
            $urls[] = route('collections.show', $collection->slug);
        }

        foreach ($listSlugs->handle() as $slug) {
            $urls[] = route('products.show', $slug);
        }

        // Drafts and scheduled content never appear here: the port only ever
        // returns what is live. The news index is listed only once there is
        // something on it.
        $content = $listContent->handle();

        foreach ($content as $entry) {
            $urls[] = $entry->type === ContentType::Post
                ? route('news.show', $entry->slug)
                : route('pages.show', $entry->slug);
        }

        if (array_any($content, static fn (ContentPageSummary $entry): bool => $entry->type === ContentType::Post)) {
            $urls[] = route('news.index');
        }

        $body = implode("\n", array_map(
            static fn (string $url): string => '    <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>',
            $urls,
        ));

        return response(
            <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                {$body}
                </urlset>
                XML,
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    public function robots(): Response
    {
        // Mirrors the `noindex` pages: nothing transactional or tied to an
        // order reference should be crawled, and crawlers shouldn't burn
        // budget on search result permutations either.
        $body = <<<TXT
            User-agent: *
            Disallow: /cart
            Disallow: /checkout
            Disallow: /account
            Disallow: /orders/lookup/result
            Disallow: /search

            Sitemap: {$this->sitemapUrl()}
            TXT;

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    private function sitemapUrl(): string
    {
        return route('sitemap');
    }
}
