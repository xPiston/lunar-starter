<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

/**
 * Smoke tests for the moderation queue - see HeroSlideResourceTest for why
 * rendering the real screen is the only way to know a Filament resource is
 * wired to the panel.
 */
final class ProductReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_the_moderation_queue_and_see_pending_reviews(): void
    {
        $this->pendingReview();

        $this->actingAs($this->staff(), 'staff')
            ->get('/lunar/product-reviews')
            ->assertOk()
            ->assertSee('A perfectly ordinary opinion');
    }

    public function test_staff_without_the_settings_permission_are_turned_away(): void
    {
        $staff = Staff::factory()->create([
            'admin' => false,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);

        $this->actingAs($staff, 'staff')
            ->get('/lunar/product-reviews')
            ->assertForbidden();
    }

    private function pendingReview(): ProductReview
    {
        return ProductReview::create([
            'product_id' => 1,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
            'body' => 'A perfectly ordinary opinion about this product.',
            'verified_purchase' => true,
            'approved_at' => null,
        ]);
    }

    private function staff(): Staff
    {
        return Staff::factory()->create([
            'admin' => true,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);
    }
}
