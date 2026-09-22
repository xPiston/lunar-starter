<?php

declare(strict_types=1);

namespace App\Http\Seo;

/**
 * Per-page SEO metadata, serialised into Inertia props and rendered as real
 * `<meta>` tags by resources/views/app.blade.php.
 *
 * Server-rendered on purpose: Inertia's own `<Head>` component patches the
 * DOM after the JS boots, which Google tolerates but every social crawler
 * (Facebook, Slack, WhatsApp, LinkedIn...) does not - they read the HTML as
 * served and never run scripts. Anything that has to survive being shared
 * has to come out of Blade.
 *
 * A presentation concern, so it deliberately lives in the Http layer: the
 * Domain has no idea what a meta tag is.
 */
final readonly class PageMeta
{
    /**
     * @param  ?string  $imageUrl  Absolute URL - relative paths are silently
     *                             dropped by crawlers.
     * @param  bool  $noindex  For pages that must never reach an index: carts,
     *                         checkout, anything behind an order reference.
     * @param  array<string, mixed>|null  $jsonLd  schema.org payload rendered as
     *                                             a ld+json script, e.g. a Product with its offer.
     * @param  array<string, string>  $ogProperties  Extra namespaced Open Graph
     *                                               properties, e.g. `product:price:amount`, which Facebook and
     *                                               Pinterest read to show a price on a shared link.
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $imageUrl = null,
        public string $type = 'website',
        public bool $noindex = false,
        public ?array $jsonLd = null,
        public array $ogProperties = [],
    ) {}

    /**
     * The fallback every page gets unless it says otherwise, so no response
     * ever ships without a description or a title.
     */
    public static function default(): self
    {
        return new self(
            title: (string) config('app.name', 'Laravel'),
            description: (string) config('seo.description'),
            imageUrl: config('seo.image'),
        );
    }

    /**
     * Flattens admin-authored HTML into a plain-text description of the length
     * search engines actually display.
     */
    public static function excerpt(?string $html, int $length = 155): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)) ?? '');

        if ($text === '') {
            return (string) config('seo.description');
        }

        return mb_strimwidth($text, 0, $length, '…');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'type' => $this->type,
            'noindex' => $this->noindex,
            'json_ld' => $this->jsonLd,
            'og_properties' => $this->ogProperties,
        ];
    }
}
