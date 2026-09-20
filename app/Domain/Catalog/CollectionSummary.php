<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

final readonly class CollectionSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
