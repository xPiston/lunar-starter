<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\HeroSlide;
use App\Domain\Content\Port\HeroSlides;

final readonly class ListHeroSlides
{
    public function __construct(private HeroSlides $slides) {}

    /**
     * @return HeroSlide[]
     */
    public function handle(): array
    {
        return $this->slides->listVisible();
    }
}
