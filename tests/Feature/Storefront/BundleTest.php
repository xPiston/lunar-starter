<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\ProductBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for bundles: several products sold together at their own
 * price.
 *
 * A bundle is one of this application's own models implementing Lunar's
 * `Purchasable`, so most of what matters is that it behaves like anything
 * else once it is in a cart - and that its availability is the scarcest part's,
 * which is the one thing a bundle does differently.
 */
final class BundleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_bundle_is_listed_with_its_price_and_what_it_saves(): void
    {
        $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 1]]);

        $this->get(route('bundles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/bundles/index')
                ->has('bundles', 1)
                ->where('bundles.0.price.formatted', '$50.00')
                ->where('bundles.0.items_total.formatted', '$49.98')
                ->has('bundles.0.items', 2)
            );
    }

    /**
     * The number that makes a bundle an offer: what the same contents cost
     * bought one by one, against what the bundle costs.
     */
    public function test_the_saving_is_the_difference_with_buying_separately(): void
    {
        $bundle = $this->bundle(priceInCents: 4000, parts: [[2499, 1], [2499, 1]]);

        $this->get(route('bundles.show', $bundle->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bundle.price.formatted', '$40.00')
                ->where('bundle.items_total.formatted', '$49.98')
                ->where('bundle.savings.formatted', '$9.98')
            );
    }

    /**
     * Three of a bundle containing two of something means six of that thing.
     * The scarcest part decides, and it has to say so before checkout.
     */
    public function test_availability_follows_the_scarcest_part(): void
    {
        // Seven of the first part, seven of the second - but the bundle takes
        // two of the second, so only three bundles can be made.
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 2]], stock: 7);

        $this->assertSame(3, $bundle->fresh()->getTotalInventory());

        $this->get(route('bundles.show', $bundle->slug))
            ->assertInertia(fn (Assert $page) => $page->where('bundle.available_stock', 3));
    }

    public function test_a_bundle_goes_into_the_cart_as_one_line(): void
    {
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 2]], stock: 7);

        $this->post(route('cart.bundles.store'), ['bundle_id' => $bundle->id, 'quantity' => 2])
            ->assertRedirect();

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cart.lines', 1)
                ->where('cart.lines.0.name', 'Starter Kit')
                ->where('cart.lines.0.quantity', 2)
                // Its own price, twice - not the sum of its parts.
                ->where('cart.lines.0.unit_price.formatted', '$50.00')
            );
    }

    /**
     * The cart line has to say what is in the box, or a basket holding a
     * bundle is a name and a price with nothing to check against.
     */
    public function test_the_cart_line_lists_the_contents(): void
    {
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 2]], stock: 7);

        $this->post(route('cart.bundles.store'), ['bundle_id' => $bundle->id]);

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines.0.options', fn (string $options): bool => str_contains($options, '2x')
                    && str_contains($options, 'Graphic T-Shirt'))
            );
    }

    public function test_more_bundles_than_the_parts_allow_are_refused(): void
    {
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 2]], stock: 7);

        $this->post(route('cart.bundles.store'), ['bundle_id' => $bundle->id, 'quantity' => 4])
            ->assertSessionHasErrors('quantity');

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->has('cart.lines', 0));
    }

    /**
     * Lunar's own CartLineStock asks whether the incoming quantity can be
     * fulfilled, ignoring what the cart already holds - so two adds of two
     * pass against three in stock. Replaced by CartLineTotalStock, which is
     * what this checks, for a bundle and for a plain product alike.
     */
    public function test_two_adds_cannot_exceed_the_stock_between_them(): void
    {
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1], [2499, 2]], stock: 7);

        $this->post(route('cart.bundles.store'), ['bundle_id' => $bundle->id, 'quantity' => 2]);
        $this->post(route('cart.bundles.store'), ['bundle_id' => $bundle->id, 'quantity' => 2])
            ->assertSessionHasErrors('quantity');

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.quantity', 2));
    }

    public function test_the_same_applies_to_a_plain_product(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499, stock: 3);
        $variantId = $this->firstVariant($product)->id;

        $this->post(route('cart.lines.store'), ['product_variant_id' => $variantId, 'quantity' => 2]);
        $this->post(route('cart.lines.store'), ['product_variant_id' => $variantId, 'quantity' => 2])
            ->assertSessionHasErrors('quantity');

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.quantity', 2));
    }

    public function test_a_retired_bundle_is_not_on_the_storefront(): void
    {
        $bundle = $this->bundle(priceInCents: 5000, parts: [[2499, 1]]);
        $bundle->update(['is_active' => false]);

        $this->get(route('bundles.show', $bundle->slug))->assertNotFound();
        $this->get(route('bundles.index'))
            ->assertInertia(fn (Assert $page) => $page->has('bundles', 0));
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $parts  [price in cents, quantity per bundle]
     */
    private function bundle(int $priceInCents, array $parts, int $stock = 100): ProductBundle
    {
        $bundle = ProductBundle::create([
            'name' => 'Starter Kit',
            'slug' => 'starter-kit',
            'description' => 'Two things that go together.',
            'is_active' => true,
        ]);

        foreach ($parts as $index => [$partPrice, $quantity]) {
            $product = $this->createDemoProduct("Graphic T-Shirt {$index}", $partPrice, stock: $stock);

            $bundle->items()->create([
                'product_variant_id' => $this->firstVariant($product)->id,
                'quantity' => $quantity,
            ]);
        }

        Price::create([
            'price' => $priceInCents,
            'currency_id' => Currency::getDefault()->id,
            'priceable_type' => $bundle->getMorphClass(),
            'priceable_id' => $bundle->id,
            'min_quantity' => 1,
        ]);

        return $bundle->refresh();
    }
}
