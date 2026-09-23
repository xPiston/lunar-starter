<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * A custom page or article, as the storefront renders it.
 *
 * `body` is HTML, written in the back office. It is trusted admin content -
 * the same status as a product description, which the product page already
 * renders as markup. Anything accepting body text from somewhere other than
 * staff would have to sanitise before it ever got here.
 */
final readonly class ContentPage
{
    public function __construct(
        public int $id,
        public ContentType $type,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public string $body,
        public ?string $imageUrl,
        /** ISO 8601. Null only for a page that was never given a date. */
        public ?string $publishedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'image_url' => $this->imageUrl,
            'published_at' => $this->publishedAt,
        ];
    }
}
