<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Content\ContentType;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

/**
 * Smoke tests for the editorial back office - see HeroSlideResourceTest for
 * why rendering the real screens is the only way to know a Filament resource
 * is wired correctly.
 */
final class ContentPageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_the_content_screen_and_see_drafts(): void
    {
        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'Behind the scenes',
            'slug' => 'behind-the-scenes',
            'body' => '<p>Draft.</p>',
            'published_at' => null,
        ]);

        // The whole point of the back office: what the storefront hides has
        // to be visible here.
        $this->actingAs($this->staff(), 'staff')
            ->get('/lunar/content-pages')
            ->assertOk()
            ->assertSee('Behind the scenes');
    }

    public function test_the_create_screen_is_reachable(): void
    {
        $this->actingAs($this->staff(), 'staff')
            ->get('/lunar/content-pages/create')
            ->assertOk();
    }

    public function test_staff_without_the_settings_permission_are_turned_away(): void
    {
        $staff = Staff::factory()->create([
            'admin' => false,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);

        $this->actingAs($staff, 'staff')
            ->get('/lunar/content-pages')
            ->assertForbidden();
    }

    private function staff(): Staff
    {
        return Staff::factory()->create([
            'admin' => true,
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);
    }
}
