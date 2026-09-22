<?php

declare(strict_types=1);

namespace App\Domain\Checkout;

final readonly class Country
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $iso2,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iso2' => $this->iso2,
        ];
    }
}
