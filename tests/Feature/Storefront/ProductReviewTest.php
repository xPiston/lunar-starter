<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Country;
use Lunar\Models\Product;
use Lunar\Stripe\Facades\Stripe;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for customer reviews, end-to-end: route -> use case ->
 * ProductReviews port -> Eloquent adapter -> Inertia page.
 *
 * The ones that matter most are about what stays invisible. A review is
 * public text written by a stranger, so nothing reaches a page before a
 * member of staff has approved it, and nothing counts towards a product's
 * rating either.
 */
final class ProductReviewTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_signed_in_customer_can_write_a_review(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('products.reviews.store', $this->slug($product)), [
                'rating' => 4,
                'body' => 'Fits exactly as described, and the fabric is heavier than I expected.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
            'approved_at' => null,
        ]);
    }

    public function test_a_guest_cannot_write_a_review(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $this->post(route('products.reviews.store', $this->slug($product)), [
            'rating' => 5,
            'body' => 'Anonymous praise, which is what a spam bot writes.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('product_reviews', 0);
    }

    /**
     * The whole moderation promise: a pending review is invisible on the
     * storefront and absent from the rating, however it got there.
     */
    public function test_a_pending_review_is_neither_shown_nor_counted(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $this->review($product, rating: 1, approved: false);

        $this->get(route('products.show', $this->slug($product)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviews', 0)
                ->where('rating.count', 0)
                ->where('rating.average', 0)
            );
    }

    public function test_an_approved_review_is_shown_and_counted(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $this->review($product, rating: 5, approved: true);
        $this->review($product, rating: 4, approved: true);
        $this->review($product, rating: 1, approved: false);

        $this->get(route('products.show', $this->slug($product)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviews', 2)
                ->where('rating.count', 2)
                ->where('rating.average', 4.5)
                ->where('rating.distribution.5', 1)
                ->where('rating.distribution.4', 1)
                ->where('rating.distribution.1', 0)
            );
    }

    public function test_the_same_customer_cannot_review_a_product_twice(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $user = User::factory()->create();

        $payload = ['rating' => 5, 'body' => 'Bought it twice, reviewing it twice would be unfair.'];

        $this->actingAs($user)->post(route('products.reviews.store', $this->slug($product)), $payload);
        $this->actingAs($user)->post(route('products.reviews.store', $this->slug($product)), $payload)
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('product_reviews', 1);
    }

    /**
     * The badge is decided from the order history when the review is written,
     * so someone who never bought the product cannot be shown as a verified
     * buyer by writing one.
     */
    public function test_a_review_without_a_matching_order_is_not_marked_as_verified(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('products.reviews.store', $this->slug($product)), [
            'rating' => 5,
            'body' => 'Writing about something I never actually ordered.',
        ]);

        $this->assertDatabaseHas('product_reviews', [
            'user_id' => $user->id,
            'verified_purchase' => false,
        ]);
    }

    /**
     * The other half of the badge: someone who did buy it is marked verified.
     * Without this, the test above would be satisfied by a feature that
     * simply never sets the flag.
     */
    public function test_a_review_from_a_real_buyer_is_marked_as_verified(): void
    {
        config(['lunar.stripe.allow_partial_payment' => true]);
        config(['lunar.stripe.sync_addresses' => false]);
        Stripe::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $this->firstVariant($product)->id,
            'quantity' => 1,
        ]);

        $address = [
            'country_id' => Country::first()->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line_one' => '10 Downing Street',
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'contact_email' => 'ada@example.com',
        ];
        $this->post(route('checkout.address'), ['billing' => $address, 'shipping' => $address]);
        $this->post(route('checkout.shipping-option'), ['identifier' => 'standard']);
        $this->post(route('checkout.complete'), ['payment_intent' => 'PI_CAPTURE']);

        $this->post(route('products.reviews.store', $this->slug($product)), [
            'rating' => 5,
            'body' => 'Ordered it, wore it, would order it again.',
        ]);

        $this->assertDatabaseHas('product_reviews', [
            'user_id' => $user->id,
            'verified_purchase' => true,
        ]);
    }

    public function test_a_review_is_rejected_when_the_rating_is_out_of_range(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $this->actingAs(User::factory()->create())
            ->post(route('products.reviews.store', $this->slug($product)), [
                'rating' => 7,
                'body' => 'Seven stars, because the scale is apparently mine to choose.',
            ])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    /**
     * Stars in a search result come from this block. An aggregateRating with
     * a count of zero is invalid, and one bad key invalidates the whole
     * thing - taking the price snippet down with it.
     */
    public function test_structured_data_carries_the_rating_only_once_there_is_one(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $slug = $this->slug($product);

        $this->assertArrayNotHasKey('aggregateRating', $this->jsonLd($slug));

        $this->review($product, rating: 4, approved: true);

        $aggregate = $this->jsonLd($slug)['aggregateRating'];
        $this->assertSame('AggregateRating', $aggregate['@type']);
        $this->assertSame(4, $aggregate['ratingValue']);
        $this->assertSame(1, $aggregate['reviewCount']);
    }

    public function test_the_page_tells_a_visitor_whether_they_may_review(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);
        $slug = $this->slug($product);
        $user = User::factory()->create();

        $this->get(route('products.show', $slug))
            ->assertInertia(fn (Assert $page) => $page->where('canReview', false));

        $this->actingAs($user)->get(route('products.show', $slug))
            ->assertInertia(fn (Assert $page) => $page->where('canReview', true));

        $this->review($product, rating: 5, approved: false, user: $user);

        $this->actingAs($user)->get(route('products.show', $slug))
            ->assertInertia(fn (Assert $page) => $page->where('canReview', false));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $slug): array
    {
        $body = $this->get(route('products.show', $slug))->getContent() ?: '';
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $matches);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function review(Product $product, int $rating, bool $approved, ?User $user = null): ProductReview
    {
        return ProductReview::create([
            'product_id' => $product->id,
            'user_id' => ($user ?? User::factory()->create())->id,
            'rating' => $rating,
            'body' => 'A perfectly ordinary opinion about this product.',
            'verified_purchase' => false,
            'approved_at' => $approved ? now() : null,
        ]);
    }

    private function slug(Product $product): string
    {
        return (string) $product->defaultUrl->slug;
    }
}
