<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for the homepage slider, end-to-end: route -> use case
 * -> HeroSlides port -> Eloquent adapter -> Inertia page. What the back
 * office writes is what the storefront shows, and nothing else.
 */
final class HeroSliderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_the_home_page_shows_visible_slides_in_their_configured_order(): void
    {
        $this->makeSlide('Second', sortOrder: 2);
        $this->makeSlide('First', sortOrder: 1);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/home')
                ->has('slides', 2)
                ->where('slides.0.title', 'First')
                ->where('slides.1.title', 'Second')
            );
    }

    public function test_a_hidden_slide_is_kept_off_the_storefront(): void
    {
        $this->makeSlide('Published');
        $this->makeSlide('Draft', isVisible: false);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('slides', 1)
                ->where('slides.0.title', 'Published')
            );
    }

    /**
     * The adapter turns a stored path into a URL - the domain never sees a
     * disk. Asserting on the shape of that URL is what stops the path leaking
     * through unchanged.
     */
    public function test_a_slide_is_published_with_a_usable_image_url(): void
    {
        $this->makeSlide('Sale', imagePath: 'hero-slides/sale.png');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('slides.0.image_url', config('app.url').'/storage/hero-slides/sale.png')
            );
    }

    /**
     * A shop that has never opened the slider screen still has a homepage:
     * the page falls back to its default heading rather than rendering an
     * empty band.
     */
    public function test_the_home_page_works_without_any_slide(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('slides', 0));
    }

    private function makeSlide(
        string $title,
        int $sortOrder = 0,
        bool $isVisible = true,
        string $imagePath = 'hero-slides/placeholder.png',
    ): HeroSlide {
        return HeroSlide::create([
            'title' => $title,
            'image_path' => $imagePath,
            'is_visible' => $isVisible,
            'sort_order' => $sortOrder,
        ]);
    }
}
