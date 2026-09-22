<?php

declare(strict_types=1);

namespace App\Domain\Content\Port;

use App\Domain\Content\HeroSlide;

/**
 * PORT: where the homepage slider's content comes from.
 *
 * Read-only on purpose. Slides are authored in the back office, which talks to
 * the storage layer directly through its own model - putting create/update
 * here would mean a second write path to keep in step with the admin panel for
 * no gain. The storefront only ever needs to display them.
 */
interface HeroSlides
{
    /**
     * Visible slides, in the order the back office arranged them.
     *
     * @return HeroSlide[]
     */
    public function listVisible(): array;
}
