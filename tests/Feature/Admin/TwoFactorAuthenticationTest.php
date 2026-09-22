<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

/**
 * Proves `AppServiceProvider::register()`'s `forceTwoFactorAuth()` call is
 * actually enforced by the panel, not just declared - a staff account with
 * no TOTP secret set up yet must be blocked from reaching the dashboard.
 */
final class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_two_factor_set_up_cannot_reach_the_dashboard(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $response = $this->actingAs($staff, 'staff')->get('/lunar');

        $response->assertRedirect();
        $this->assertStringContainsString('multi-factor', $response->headers->get('Location'));
    }

    public function test_staff_with_two_factor_set_up_can_reach_the_dashboard(): void
    {
        $staff = Staff::factory()->create([
            'admin' => true,
            // Cast to 'encrypted' by Staff's own InteractsWithAppAuthentication
            // trait - assigning the plain value here is correct, Eloquent
            // encrypts it on save.
            'app_authentication_secret' => 'a-fake-totp-secret',
        ]);

        $this->actingAs($staff, 'staff')
            ->get('/lunar')
            ->assertOk();
    }
}
