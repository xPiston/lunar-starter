<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Content;

use App\Domain\Content\ContentPage;
use App\Domain\Content\ContentPageSummary;
use App\Domain\Content\ContentType;
use App\Domain\Content\Port\ContentPages;
use App\Models\ContentPage as ContentPageRecord;
use Illuminate\Support\Facades\Storage;

/**
 * ADAPTER: serves custom pages and articles from the application's own table.
 *
 * Every read goes through the model's `published` scope, so "published" is
 * enforced in one place rather than at each call site - a controller cannot
 * forget it and expose a draft.
 */
final readonly class EloquentContentPages implements ContentPages
{
    public function listPublished(?ContentType $type = null, ?int $limit = null): array
    {
        return ContentPageRecord::query()
            ->published()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderByDesc('published_at')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(fn (ContentPageRecord $page): ContentPageSummary => new ContentPageSummary(
                id: $page->id,
                type: $page->type,
                title: $page->title,
                slug: $page->slug,
                excerpt: $page->excerpt,
                imageUrl: $this->imageUrl($page),
                publishedAt: $page->published_at?->toIso8601String(),
            ))
            ->all();
    }

    public function find(string $slug, ContentType $type): ?ContentPage
    {
        $page = ContentPageRecord::query()
            ->published()
            ->where('type', $type)
            ->where('slug', $slug)
            ->first();

        if ($page === null) {
            return null;
        }

        return new ContentPage(
            id: $page->id,
            type: $page->type,
            title: $page->title,
            slug: $page->slug,
            excerpt: $page->excerpt,
            body: $page->body,
            imageUrl: $this->imageUrl($page),
            publishedAt: $page->published_at?->toIso8601String(),
        );
    }

    private function imageUrl(ContentPageRecord $page): ?string
    {
        return $page->image_path === null
            ? null
            : Storage::disk('public')->url($page->image_path);
    }
}
