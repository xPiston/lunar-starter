<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Content;

use App\Domain\Content\HeroSlide;
use App\Domain\Content\Port\HeroSlides;
use App\Models\HeroSlide as HeroSlideRecord;
use Illuminate\Support\Facades\Storage;

/**
 * ADAPTER: serves the homepage slider from the application's own table.
 *
 * Under app/Infrastructure/Eloquent rather than .../Lunar because nothing here
 * is Lunar's: a slider is editorial content, and the port would be satisfied
 * just as well by a CMS or a flat file. The layering rule is about the domain
 * staying free of any particular storage, not about everything going through
 * Lunar.
 *
 * The record is aliased to make the one translation this class exists for
 * obvious: a HeroSlideRecord (a row, holding a storage path) becomes a
 * HeroSlide (a value object, holding a URL).
 */
final readonly class EloquentHeroSlides implements HeroSlides
{
    public function listVisible(): array
    {
        return HeroSlideRecord::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            // Ties are broken by id so the order never depends on however the
            // database happens to return equal rows.
            ->orderBy('id')
            ->get()
            ->map(fn (HeroSlideRecord $slide): HeroSlide => new HeroSlide(
                id: $slide->id,
                title: $slide->title,
                subtitle: $slide->subtitle,
                imageUrl: Storage::disk('public')->url($slide->image_path),
                linkUrl: $slide->link_url,
            ))
            ->all();
    }
}
