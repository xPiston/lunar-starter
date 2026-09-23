<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContentPageResource\Pages;

use App\Filament\Resources\ContentPageResource;
use Filament\Actions\DeleteAction;
use Lunar\Admin\Support\Pages\BaseEditRecord;

class EditContentPage extends BaseEditRecord
{
    protected static string $resource = ContentPageResource::class;

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
