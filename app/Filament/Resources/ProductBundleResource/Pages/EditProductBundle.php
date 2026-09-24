<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductBundleResource\Pages;

use App\Filament\Resources\ProductBundleResource;
use App\Filament\Resources\ProductBundleResource\Pages\Concerns\EditsBundlePrice;
use App\Models\ProductBundle;
use Filament\Actions\DeleteAction;
use Lunar\Admin\Support\Pages\BaseEditRecord;

class EditProductBundle extends BaseEditRecord
{
    use EditsBundlePrice;

    protected static string $resource = ProductBundleResource::class;

    protected mixed $bundlePrice = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProductBundle $record */
        $record = $this->getRecord();
        $data['bundle_price'] = $this->priceFor($record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->bundlePrice = $data['bundle_price'] ?? null;
        unset($data['bundle_price']);

        return $data;
    }

    public function afterSave(): void
    {
        /** @var ProductBundle $record */
        $record = $this->getRecord();

        $this->storePrice($record, $this->bundlePrice);
    }

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
