<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContentPageResource\Pages;

use App\Filament\Resources\ContentPageResource;
use Lunar\Admin\Support\Pages\BaseCreateRecord;

class CreateContentPage extends BaseCreateRecord
{
    protected static string $resource = ContentPageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
