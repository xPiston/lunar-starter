<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for quantity pricing: the more you order, the less each one
 * costs.
 *
 * Lunar already does the pricing - `lunar_prices.min_quantity` is its own
 * price-break mechanism and its pricing manager charges the right step. What
 * these check is that the storefront shows the same numbers the cart will
 * charge, because a pack card promising a price the basket then contradicts
 * is worse than no pack card at all.
 */
final class PriceTierTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_product_without_price_breaks_offers_no_packs(): void
    {
        $product = $this->createDemoProduct('Graphic T-Shirt', 2499);

        $this->get(route('products.show', $this->slug($product)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('product.variants.0.tiers', 0));
    }

    public function test_each_step_shows_its_unit_price_its_total_and_what_it_saves(): void
    {
        $product = $this->withPriceBreaks(unit: 3400, breaks: [3 => 3060, 5 => 2720]);

        $this->get(route('products.show', $this->slug($product)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // The single unit leads: a customer compares packs against
                // buying one.
                ->has('product.variants.0.tiers', 3)
                ->where('product.variants.0.tiers.0.quantity', 1)
                ->where('product.variants.0.tiers.0.total.formatted', '$34.00')
                ->where('product.variants.0.tiers.0.percent_off', 0)

                ->where('product.variants.0.tiers.1.quantity', 3)
                ->where('product.variants.0.tiers.1.unit_price.formatted', '$30.60')
                ->where('product.variants.0.tiers.1.total.formatted', '$91.80')
                ->where('product.variants.0.tiers.1.undiscounted_total.formatted', '$102.00')
                ->where('product.variants.0.tiers.1.percent_off', 10)

                ->where('product.variants.0.tiers.2.quantity', 5)
                ->where('product.variants.0.tiers.2.total.formatted', '$136.00')
                ->where('product.variants.0.tiers.2.percent_off', 20)
            );
    }

    /**
     * The whole promise. A card saying $91.80 for three has to be what the
     * basket charges, and that comes from Lunar rather than from anything
     * this template computes.
     */
    public function test_the_cart_charges_the_step_the_page_advertised(): void
    {
        $product = $this->withPriceBreaks(unit: 3400, breaks: [3 => 3060, 5 => 2720]);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $this->firstVariant($product)->id,
            'quantity' => 3,
        ]);

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.lines.0.unit_price.formatted', '$30.60')
                // The cart's sub total, not the line total: Lunar reports a
                // line total with tax included ($110.16 here at 20%) while
                // unit prices and the sub total are tax-exclusive. That mix
                // predates this feature and is asserted rather than hidden,
                // so a change to it fails loudly.
                ->where('cart.sub_total.formatted', '$91.80')
                ->where('cart.lines.0.line_total.formatted', '$110.16')
            );
    }

    /**
     * A step applies from its quantity upwards, not only at it: four boxes
     * are priced at the three-box rate, which is what "3+" means.
     */
    public function test_a_quantity_between_two_steps_gets_the_lower_one(): void
    {
        $product = $this->withPriceBreaks(unit: 3400, breaks: [3 => 3060, 5 => 2720]);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $this->firstVariant($product)->id,
            'quantity' => 4,
        ]);

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.unit_price.formatted', '$30.60'));
    }

    public function test_below_the_first_step_the_ordinary_price_applies(): void
    {
        $product = $this->withPriceBreaks(unit: 3400, breaks: [3 => 3060]);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $this->firstVariant($product)->id,
            'quantity' => 2,
        ]);

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.unit_price.formatted', '$34.00'));
    }

    /**
     * A saving of 9.6% advertises 9%, never 10%: rounding a discount up is
     * the kind of small overstatement a price label must not make.
     */
    public function test_the_advertised_percentage_is_rounded_down(): void
    {
        $product = $this->withPriceBreaks(unit: 1000, breaks: [2 => 904]);

        $this->get(route('products.show', $this->slug($product)))
            ->assertInertia(fn (Assert $page) => $page->where('product.variants.0.tiers.1.percent_off', 9));
    }

    /**
     * @param  array<int, int>  $breaks  quantity => unit price in cents
     */
    private function withPriceBreaks(int $unit, array $breaks): Product
    {
        $product = $this->createDemoProduct('Creatine Sticks', $unit, stock: 100);
        $variant = $this->firstVariant($product);

        foreach ($breaks as $quantity => $price) {
            $variant->prices()->create([
                'currency_id' => Currency::getDefault()->id,
                'price' => $price,
                'min_quantity' => $quantity,
            ]);
        }

        return $product->refresh();
    }

    private function slug(Product $product): string
    {
        return (string) $product->defaultUrl->slug;
    }
}
