<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * One slide of the homepage slider, as the storefront needs it.
 *
 * Carries an image URL rather than a storage path: which disk holds the file
 * and how it is served is an infrastructure detail, resolved by the adapter.
 */
final readonly class HeroSlide
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $subtitle,
        public string $imageUrl,
        public ?string $linkUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->imageUrl,
            'link_url' => $this->linkUrl,
        ];
    }
}
