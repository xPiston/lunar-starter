<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Lunar\Admin\Support\Pages\BaseCreateRecord;

class CreateHeroSlide extends BaseCreateRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
