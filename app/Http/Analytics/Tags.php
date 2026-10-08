<?php

declare(strict_types=1);

namespace App\Http\Analytics;

/**
 * The analytics identifiers that reach the page, already validated.
 *
 * These end up inside a <script> block, so they are not merely read from the
 * environment and printed: an id that does not look exactly like an id is
 * dropped. Blade escaping protects the HTML, not the JavaScript around it, and
 * `GTM-X');evil('` is a perfectly valid environment variable.
 *
 * Both are null by default. A checkout that nobody has configured analytics
 * for ships no third-party script at all - which is also what makes the test
 * suite and local development free of them.
 */
final readonly class Tags
{
    /**
     * Google Tag Manager container ids: `GTM-` then uppercase alphanumerics.
     */
    private const string GTM_PATTERN = '/^GTM-[A-Z0-9]{4,}$/';

    /**
     * GA4 measurement ids: `G-` then uppercase alphanumerics. The older
     * `UA-` properties stopped collecting in 2023 and are not accepted.
     */
    private const string GA4_PATTERN = '/^G-[A-Z0-9]{4,}$/';

    public function __construct(
        public ?string $googleTagManagerId = null,
        public ?string $googleAnalyticsId = null,
        public bool $requireConsent = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            googleTagManagerId: self::validate(config('analytics.google_tag_manager_id'), self::GTM_PATTERN),
            googleAnalyticsId: self::validate(config('analytics.google_analytics_id'), self::GA4_PATTERN),
            requireConsent: (bool) config('analytics.require_consent', true),
        );
    }

    /**
     * Whether anything at all has to be rendered.
     */
    public function enabled(): bool
    {
        return $this->googleTagManagerId !== null || $this->googleAnalyticsId !== null;
    }

    /**
     * Whether the visitor has a choice worth being asked for.
     *
     * Both halves matter. No tag means nothing to consent to, and a banner
     * that sets no cookie either way is theatre. Consent not required means
     * the tags were granted everything before the page loaded, and asking
     * afterwards would be a lie.
     */
    public function consentRequired(): bool
    {
        return $this->enabled() && $this->requireConsent;
    }

    private static function validate(mixed $value, string $pattern): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match($pattern, $value) === 1 ? $value : null;
    }
}
