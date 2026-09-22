<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * SEO metadata has to survive in the HTML as served: social crawlers never
 * run JavaScript, so asserting on the Inertia props would prove nothing.
 * Every assertion here reads the rendered response body.
 */
final class SeoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_product_page_carries_sharable_metadata(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $slug = (string) $product->defaultUrl->slug;

        $response = $this->get(route('products.show', $slug));

        $response->assertOk()
            ->assertSee('<meta property="og:title" content="Graphic T-Shirt">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta name="twitter:card"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('<meta name="description"', false);

        $this->assertStringNotContainsString('name="robots"', $response->getContent() ?: '');
    }

    public function test_a_product_page_exposes_price_and_availability_as_structured_data(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499, stock: 5);
        $slug = (string) $product->defaultUrl->slug;

        $body = $this->get(route('products.show', $slug))->getContent() ?: '';

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $matches);
        $this->assertNotEmpty($matches, 'The product page should embed a JSON-LD block.');

        /** @var array<string, mixed> $jsonLd */
        $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Product', $jsonLd['@type']);
        $this->assertSame('Graphic T-Shirt', $jsonLd['name']);
        $this->assertSame('24.99', $jsonLd['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $jsonLd['offers']['availability']);
    }

    public function test_an_out_of_stock_product_says_so_in_its_structured_data(): void
    {
        $product = $this->createDemoProduct('Sold Out Tee', 2499, stock: 0);
        $slug = (string) $product->defaultUrl->slug;

        $body = $this->get(route('products.show', $slug))->getContent() ?: '';
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $matches);
        $jsonLd = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org/OutOfStock', $jsonLd['offers']['availability']);
    }

    /**
     * Carts, checkout and anything reached through an order reference must
     * never be indexed - that's the whole reason PageMeta carries a noindex
     * flag.
     */
    public function test_transactional_pages_are_kept_out_of_the_index(): void
    {
        foreach ([route('cart.show'), route('checkout.show'), route('search', ['q' => 'tee'])] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    public function test_the_sitemap_lists_published_products_and_collections(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $slug = (string) $product->defaultUrl->slug;

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('products.show', $slug), false)
            ->assertSee(route('home'), false);
    }

    public function test_robots_points_at_the_sitemap_and_blocks_private_paths(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'), false)
            ->assertSee('Disallow: /checkout', false);
    }
}
