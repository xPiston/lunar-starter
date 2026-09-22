<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;

class ListHeroSlides extends BaseListRecords
{
    protected static string $resource = HeroSlideResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
