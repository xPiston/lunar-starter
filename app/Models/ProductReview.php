<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Storage record for a customer review. Infrastructure, like HeroSlide and
 * ContentPage: the storefront reaches it through the ProductReviews port, and
 * only the back office edits this model directly.
 *
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property int $rating
 * @property string $body
 * @property bool $verified_purchase
 * @property ?Carbon $approved_at
 */
class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'body',
        'verified_purchase',
        'approved_at',
    ];

    /**
     * Visible to the public. Every storefront read goes through this, so an
     * unmoderated review cannot reach a page by way of a forgotten condition.
     *
     * @param  Builder<ProductReview>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}
