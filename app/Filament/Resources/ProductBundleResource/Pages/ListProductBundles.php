<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductBundleResource\Pages;

use App\Filament\Resources\ProductBundleResource;
use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;

class ListProductBundles extends BaseListRecords
{
    protected static string $resource = ProductBundleResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
