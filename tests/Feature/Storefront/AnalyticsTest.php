<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The analytics tags are rendered into the HTML as served, so every assertion
 * here reads the response body rather than the Inertia props.
 *
 * What is worth pinning: that nothing is served when nothing is configured,
 * that an id which is not an id never reaches a <script>, and that consent is
 * denied before a tag loads rather than after.
 */
final class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_served_when_no_id_is_configured(): void
    {
        config(['analytics.google_tag_manager_id' => null, 'analytics.google_analytics_id' => null]);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringNotContainsString('googletagmanager.com', $body);
        $this->assertStringNotContainsString('dataLayer', $body);
        $this->assertStringNotContainsString("gtag('consent'", $body);
    }

    public function test_tag_manager_is_served_in_the_head_and_after_the_body(): void
    {
        config(['analytics.google_tag_manager_id' => 'GTM-ABC1234']);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringContainsString("'script','dataLayer','GTM-ABC1234'", $body);
        $this->assertStringContainsString(
            '<iframe src="https://www.googletagmanager.com/ns.html?id=GTM-ABC1234"',
            $body,
        );

        // The noscript half is useless anywhere but immediately after <body>.
        $this->assertLessThan(
            (int) strpos($body, '<div id="app"'),
            (int) strpos($body, 'googletagmanager.com/ns.html'),
        );
    }

    public function test_ga4_is_served_through_gtag(): void
    {
        config(['analytics.google_analytics_id' => 'G-ABCD123456']);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringContainsString('gtag/js?id=G-ABCD123456', $body);
        $this->assertStringContainsString("gtag('config', 'G-ABCD123456')", $body);
    }

    public function test_the_tags_load_before_the_application_bundle(): void
    {
        config(['analytics.google_tag_manager_id' => 'GTM-ABC1234']);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        // A tag that loads after the page it measures misses whoever leaves
        // first, so the order in the head is part of the behaviour. The marker
        // is the entry chunk Vite emits, which is the first thing of ours the
        // browser fetches.
        $bundle = strpos($body, 'build/assets/app-');

        $this->assertNotFalse($bundle, 'the page did not reference the built bundle');
        $this->assertLessThan($bundle, (int) strpos($body, 'googletagmanager.com/gtm.js'));
    }

    public function test_consent_is_denied_before_any_tag_loads(): void
    {
        config([
            'analytics.google_tag_manager_id' => 'GTM-ABC1234',
            'analytics.require_consent' => true,
        ]);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringContainsString("'analytics_storage': 'denied'", $body);
        $this->assertStringContainsString("'ad_storage': 'denied'", $body);

        // A tag that boots with no consent state assumes it has consent, so
        // "before" is the whole point: after the container, this does nothing.
        $this->assertLessThan(
            (int) strpos($body, 'googletagmanager.com/gtm.js'),
            (int) strpos($body, "gtag('consent', 'default'"),
        );
    }

    public function test_consent_defaults_are_absent_when_not_required(): void
    {
        config([
            'analytics.google_tag_manager_id' => 'GTM-ABC1234',
            'analytics.require_consent' => false,
        ]);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringContainsString('googletagmanager.com/gtm.js', $body);
        $this->assertStringNotContainsString("gtag('consent', 'default'", $body);
    }

    /**
     * The flag the consent banner is mounted on. A banner with nothing to ask
     * is worse than none: it teaches people to dismiss the real ones.
     *
     * @return array<string, array{?string, bool, bool}>
     */
    public static function consentCases(): array
    {
        return [
            'no tag at all' => [null, true, false],
            'a tag waiting on consent' => ['GTM-ABC1234', true, true],
            'a tag already granted everything' => ['GTM-ABC1234', false, false],
        ];
    }

    #[DataProvider('consentCases')]
    public function test_the_consent_banner_is_offered_only_when_there_is_a_choice(
        ?string $containerId,
        bool $requireConsent,
        bool $expected,
    ): void {
        config([
            'analytics.google_tag_manager_id' => $containerId,
            'analytics.google_analytics_id' => null,
            'analytics.require_consent' => $requireConsent,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('analyticsConsentRequired', $expected));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedIds(): array
    {
        return [
            'injection through the container id' => ["GTM-X');alert(1);('"],
            'lowercase' => ['gtm-abc1234'],
            'wrong prefix' => ['GTX-ABC1234'],
            'a bare word' => ['yes'],
            'a universal analytics property' => ['UA-123456-1'],
        ];
    }

    #[DataProvider('malformedIds')]
    public function test_an_id_that_is_not_an_id_never_reaches_a_script(string $id): void
    {
        config(['analytics.google_tag_manager_id' => $id, 'analytics.google_analytics_id' => $id]);

        $body = $this->get('/')->assertOk()->getContent() ?: '';

        $this->assertStringNotContainsString('googletagmanager.com', $body);
        $this->assertStringNotContainsString($id, $body);
    }
}
