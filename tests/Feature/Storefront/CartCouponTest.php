<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Discount;
use Tests\Concerns\SeedsLunarStorefront;
use Tests\TestCase;

/**
 * CONTRACT tests for coupon codes: `Cart::coupon_code` is a real column
 * Lunar's own `ApplyDiscounts` pipeline already reads on every recalculate
 * (see LunarCartGateway::applyCoupon()) - these tests prove OUR wiring
 * (validation, the "doesn't apply to this cart" revert, the storefront
 * form) rather than Lunar's discount-matching logic itself.
 *
 * `Lunar\Base\Traits\HasChannels`/`HasCustomerGroups` auto-enable a new
 * Discount for the default channel/customer group on creation, so a bare
 * `Discount::create()` here is already usable - no manual pivot setup
 * needed.
 */
final class CartCouponTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLunarStorefront;

    public function test_a_valid_coupon_reduces_the_cart_total(): void
    {
        $this->createPercentageCoupon('SAVE10', 10);
        $this->addProductToCart('T-shirt', 2499);

        $this->post(route('cart.coupon.store'), ['code' => 'save10'])->assertRedirect();

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.coupon_code', 'SAVE10')
                // 10% of $24.99 = $2.499, rounded to the nearest cent by
                // Lunar\DiscountTypes\AmountOff::applyPercentage().
                ->where('cart.discount_total.minor_amount', 250)
            );
    }

    public function test_an_unknown_coupon_is_rejected(): void
    {
        $this->addProductToCart('T-shirt', 2499);

        $this->post(route('cart.coupon.store'), ['code' => 'DOES-NOT-EXIST'])
            ->assertSessionHasErrors(['coupon' => 'This coupon code is invalid or has expired.']);

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.coupon_code', null)
                ->where('cart.discount_total.minor_amount', 0)
            );
    }

    public function test_a_coupon_that_does_not_apply_to_the_cart_is_rejected_and_not_left_applied(): void
    {
        // A coupon that requires a cart subtotal far beyond what's in it -
        // Lunar\DiscountTypes\AbstractDiscountType::checkDiscountConditions()
        // fails its min-spend check, so the discount type never applies
        // anything, exactly like a product-restricted coupon on the wrong cart.
        $this->createPercentageCoupon('BIGSPENDER', 10, minSpendMinorUnits: 100_000_00);
        $this->addProductToCart('T-shirt', 2499);

        $this->post(route('cart.coupon.store'), ['code' => 'BIGSPENDER'])
            ->assertSessionHasErrors(['coupon' => "This coupon code doesn't apply to the items in your cart."]);

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.coupon_code', null)
                ->where('cart.discount_total.minor_amount', 0)
            );
    }

    public function test_removing_an_applied_coupon_restores_the_full_total(): void
    {
        $this->createPercentageCoupon('SAVE10', 10);
        $this->addProductToCart('T-shirt', 2499);
        $this->post(route('cart.coupon.store'), ['code' => 'SAVE10']);

        $this->delete(route('cart.coupon.destroy'))->assertRedirect();

        $this->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.coupon_code', null)
                ->where('cart.discount_total.minor_amount', 0)
            );
    }

    private function addProductToCart(string $name, int $priceInCents): void
    {
        $product = $this->createDemoProduct($name, $priceInCents);
        $variant = $this->firstVariant($product);

        $this->post(route('cart.lines.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    private function createPercentageCoupon(string $code, float $percentage, ?int $minSpendMinorUnits = null): void
    {
        Discount::create([
            'name' => $code,
            'handle' => strtolower($code),
            'coupon' => $code,
            'type' => AmountOff::class,
            'starts_at' => now()->subDay(),
            'data' => array_filter([
                'percentage' => $percentage,
                'min_prices' => $minSpendMinorUnits ? ['USD' => $minSpendMinorUnits] : null,
            ]),
        ]);
    }
}
