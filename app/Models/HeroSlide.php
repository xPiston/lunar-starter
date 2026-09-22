<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Storage record for a homepage slide. This is infrastructure, not the domain:
 * the storefront never sees it, it reaches App\Domain\Content\HeroSlide
 * through the HeroSlides port. The back office, which is Filament talking to
 * Eloquent, edits this model directly.
 *
 * @property int $id
 * @property string $title
 * @property ?string $subtitle
 * @property string $image_path
 * @property ?string $link_url
 * @property bool $is_visible
 * @property int $sort_order
 */
class HeroSlide extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image_path',
        'link_url',
        'is_visible',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
