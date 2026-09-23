<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Content\ContentType;
use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the editorial module, end-to-end: route -> use case ->
 * ContentPages port -> Eloquent adapter -> Inertia page.
 *
 * Most of these are about what must NOT come out: a draft, a scheduled post,
 * or a page reached through the wrong URL shape are all 404, and none of them
 * may appear in a listing, the navigation or the sitemap.
 */
final class ContentPagesTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_the_news_index_lists_published_articles_newest_first(): void
    {
        $this->makeArticle('Older', publishedAt: now()->subWeek());
        $this->makeArticle('Newer', publishedAt: now()->subDay());

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/news/index')
                ->has('articles', 2)
                ->where('articles.0.title', 'Newer')
                ->where('articles.1.title', 'Older')
            );
    }

    public function test_a_draft_article_is_invisible_to_the_storefront(): void
    {
        $draft = $this->makeDraft('Draft');

        $this->get(route('news.show', $draft->slug))->assertNotFound();
        $this->get(route('news.index'))->assertInertia(fn (Assert $page) => $page->has('articles', 0));
    }

    /**
     * A future date is a schedule, not a publication: the same row becomes
     * visible on its own once that moment passes, with nothing to run.
     */
    public function test_an_article_dated_in_the_future_stays_hidden_until_then(): void
    {
        $article = $this->makeArticle('Embargoed', publishedAt: now()->addDay());

        $this->get(route('news.show', $article->slug))->assertNotFound();

        $this->travel(2)->days();

        $this->get(route('news.show', $article->slug))->assertOk();
    }

    /**
     * The two URL shapes are not interchangeable - a page must not answer
     * under /news, or its article metadata and dated layout would apply to
     * something that isn't one.
     */
    public function test_the_two_content_types_do_not_answer_at_each_other_urls(): void
    {
        $page = $this->makePage('About us');
        $article = $this->makeArticle('Autumn');

        $this->get(route('pages.show', $page->slug))->assertOk();
        $this->get(route('news.show', $page->slug))->assertNotFound();

        $this->get(route('news.show', $article->slug))->assertOk();
        $this->get(route('pages.show', $article->slug))->assertNotFound();
    }

    public function test_a_custom_page_renders_its_body(): void
    {
        $page = $this->makePage('Shipping', body: '<p>Two working days.</p>');

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('storefront/content-page')
                ->where('page.title', 'Shipping')
                ->where('page.body', '<p>Two working days.</p>')
            );
    }

    /**
     * Sharing an article has to produce an article card, not a generic site
     * one - and crawlers read the served HTML, never the Inertia props.
     */
    public function test_an_article_carries_article_metadata(): void
    {
        $article = $this->makeArticle('Autumn collection', publishedAt: now()->subDay());

        $body = $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="article:published_time"', false)
            ->getContent() ?: '';

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $matches);
        $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('BlogPosting', $jsonLd['@type']);
        $this->assertSame('Autumn collection', $jsonLd['headline']);
    }

    public function test_the_sitemap_lists_published_content_only(): void
    {
        $article = $this->makeArticle('Autumn');
        $page = $this->makePage('About us');
        $draft = $this->makeDraft('Draft');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('news.show', $article->slug), false)
            ->assertSee(route('pages.show', $page->slug), false)
            ->assertSee(route('news.index'), false)
            ->assertDontSee(route('news.show', $draft->slug), false);
    }

    /**
     * The News tab is worth a permanent place in the navbar only once there
     * is something under it; custom pages are advertised in the footer.
     */
    public function test_the_navigation_only_offers_news_once_an_article_exists(): void
    {
        $this->makePage('About us');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navContent.has_news', false)
                ->has('navContent.pages', 1)
                ->where('navContent.pages.0.title', 'About us')
            );

        $this->makeArticle('Autumn');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('navContent.has_news', true));
    }

    /**
     * Those links are shared for the storefront chrome only: a dashboard or
     * auth page must not pay for the query behind them.
     */
    public function test_content_links_are_not_shared_off_the_storefront(): void
    {
        $this->makeArticle('Autumn');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navContent.has_news', false)
                ->has('navContent.pages', 0)
            );
    }

    /**
     * Published yesterday unless told otherwise - the common case. A date in
     * the future schedules it; makeDraft() below is the undated one.
     */
    private function makeArticle(string $title, ?Carbon $publishedAt = null, string $body = '<p>Body.</p>'): ContentPage
    {
        return ContentPage::create([
            'type' => ContentType::Post,
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'body' => $body,
            'published_at' => $publishedAt ?? now()->subDay(),
        ]);
    }

    private function makeDraft(string $title): ContentPage
    {
        return ContentPage::create([
            'type' => ContentType::Post,
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'body' => '<p>Body.</p>',
            'published_at' => null,
        ]);
    }

    private function makePage(string $title, string $body = '<p>Body.</p>'): ContentPage
    {
        return ContentPage::create([
            'type' => ContentType::Page,
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'body' => $body,
            'published_at' => now()->subDay(),
        ]);
    }
}
