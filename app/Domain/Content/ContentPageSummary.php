<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * A piece of content as a listing needs it: enough for a card or a nav link,
 * without the body. `ContentPage` is the full thing.
 */
final readonly class ContentPageSummary
{
    public function __construct(
        public int $id,
        public ContentType $type,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $imageUrl,
        /** ISO 8601, so the frontend can format it and JSON-LD can use it verbatim. */
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
            'image_url' => $this->imageUrl,
            'published_at' => $this->publishedAt,
        ];
    }
}
