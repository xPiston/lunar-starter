<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductReviewResource\Pages;

use App\Filament\Resources\ProductReviewResource;
use Lunar\Admin\Support\Pages\BaseListRecords;

class ListProductReviews extends BaseListRecords
{
    protected static string $resource = ProductReviewResource::class;

    /**
     * No "create" action: a review comes from a customer on the storefront,
     * never from staff.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [];
    }
}
