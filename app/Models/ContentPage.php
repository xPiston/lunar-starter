<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Storage record for a custom page or article. Infrastructure, like
 * App\Models\HeroSlide: the storefront reaches it through the ContentPages
 * port, only the back office touches this class.
 *
 * @property int $id
 * @property ContentType $type
 * @property string $title
 * @property string $slug
 * @property ?string $excerpt
 * @property string $body
 * @property ?string $image_path
 * @property ?Carbon $published_at
 */
class ContentPage extends Model
{
    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'image_path',
        'published_at',
    ];

    /**
     * Live content: dated, and that date has passed. Used by the adapter for
     * every storefront read, so a draft can never leak through one of them.
     *
     * @param  Builder<ContentPage>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'published_at' => 'datetime',
        ];
    }
}
