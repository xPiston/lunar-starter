<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * What a piece of editorial content is for, which is also what decides where
 * it lives on the storefront.
 *
 * One entity with a type rather than two near-identical ones: an article and
 * a standalone page differ only in whether they are listed and dated, not in
 * what they hold.
 */
enum ContentType: string
{
    /** Standalone page, reachable by its URL and linked from the footer. */
    case Page = 'page';

    /** Dated article, listed newest first under /news. */
    case Post = 'post';
}
