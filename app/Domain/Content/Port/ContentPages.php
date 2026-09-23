<?php

declare(strict_types=1);

namespace App\Domain\Content\Port;

use App\Domain\Content\ContentPage;
use App\Domain\Content\ContentPageSummary;
use App\Domain\Content\ContentType;

/**
 * PORT: the editorial content the storefront can show - custom pages and
 * articles.
 *
 * Read-only, for the same reason as HeroSlides: authoring happens in the back
 * office, against its own model. "Published" is this port's responsibility,
 * not the caller's - nothing that reaches the storefront through here is a
 * draft or dated in the future.
 */
interface ContentPages
{
    /**
     * Published content, newest first.
     *
     * @param  ContentType|null  $type  null returns every type at once, which
     *                                  is what the navigation and the sitemap
     *                                  need - one query instead of two.
     * @return ContentPageSummary[]
     */
    public function listPublished(?ContentType $type = null, ?int $limit = null): array;

    /**
     * One published page or article, or null when it doesn't exist, isn't
     * published yet, or isn't of that type - the storefront treats all three
     * the same way, as a 404.
     */
    public function find(string $slug, ContentType $type): ?ContentPage;
}
