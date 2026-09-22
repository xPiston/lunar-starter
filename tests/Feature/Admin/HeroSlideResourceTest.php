<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

/**
 * Smoke tests for the slider's back office. The storefront tests prove slides
 * are published correctly once they exist; these prove they can actually be
 * managed - a Filament resource is only ever wrong at runtime, so rendering
 * the real page is the only way to know it is wired to the panel.
 */
final class HeroSlideResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_the_slider_screen(): void
    {
        HeroSlide::create([
            'title' => 'New season, new arrivals',
            'image_path' => 'hero-slides/placeholder.png',
        ]);

        $this->actingAs($this->staff(), 'staff')
            ->get('/lunar/hero-slides')
            ->assertOk()
            ->assertSee('New season, new arrivals');
    }

    public function test_the_create_screen_is_reachable(): void
    {
        $this->actingAs($this->staff(), 'staff')
            ->get('/lunar/hero-slides/create')
            ->assertOk();
    }

    /**
     * The resource declares the `settings` permission: staff without it must
     * not reach the screen. Without this, adding a resource to Lunar's panel
     * silently opens it to every staff account.
     */
    public function test_staff_without_the_settings_permission_are_turned_away(): void
    {
        $staff = Staff::factory()->create([
            'admin' => false,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);

        $this->actingAs($staff, 'staff')
            ->get('/lunar/hero-slides')
            ->assertForbidden();
    }

    private function staff(): Staff
    {
        // TOTP secret set because the panel forces two-factor auth - see
        // TwoFactorAuthenticationTest for why that is not optional here.
        return Staff::factory()->create([
            'admin' => true,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);
    }
}
