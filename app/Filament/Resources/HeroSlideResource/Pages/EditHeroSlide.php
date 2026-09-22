<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Actions\DeleteAction;
use Lunar\Admin\Support\Pages\BaseEditRecord;

class EditHeroSlide extends BaseEditRecord
{
    protected static string $resource = HeroSlideResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
