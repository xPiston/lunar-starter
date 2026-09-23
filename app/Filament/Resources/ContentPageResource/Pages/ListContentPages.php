<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContentPageResource\Pages;

use App\Filament\Resources\ContentPageResource;
use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;

class ListContentPages extends BaseListRecords
{
    protected static string $resource = ContentPageResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
