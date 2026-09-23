<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Domain\Content\ContentPage;
use App\Domain\Content\ContentType;
use App\Domain\Content\Port\ContentPages;

final readonly class ShowContentPage
{
    public function __construct(private ContentPages $pages) {}

    /**
     * @throws ContentPageNotFoundException
     */
    public function handle(string $slug, ContentType $type): ContentPage
    {
        return $this->pages->find($slug, $type)
            ?? throw ContentPageNotFoundException::forSlug($slug);
    }
}
