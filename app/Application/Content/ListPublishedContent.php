<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\ContentPageSummary;
use App\Domain\Content\ContentType;
use App\Domain\Content\Port\ContentPages;

final readonly class ListPublishedContent
{
    public function __construct(private ContentPages $pages) {}

    /**
     * @return ContentPageSummary[]
     */
    public function handle(?ContentType $type = null, ?int $limit = null): array
    {
        return $this->pages->listPublished($type, $limit);
    }
}
