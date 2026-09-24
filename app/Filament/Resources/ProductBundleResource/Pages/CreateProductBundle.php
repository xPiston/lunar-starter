<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductBundleResource\Pages;

use App\Filament\Resources\ProductBundleResource;
use App\Filament\Resources\ProductBundleResource\Pages\Concerns\EditsBundlePrice;
use App\Models\ProductBundle;
use Lunar\Admin\Support\Pages\BaseCreateRecord;

class CreateProductBundle extends BaseCreateRecord
{
    use EditsBundlePrice;

    protected static string $resource = ProductBundleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Held aside because it is not a column on the bundle - see the trait.
        $this->bundlePrice = $data['bundle_price'] ?? null;
        unset($data['bundle_price']);

        return $data;
    }

    public function afterCreate(): void
    {
        /** @var ProductBundle $record */
        $record = $this->getRecord();

        $this->storePrice($record, $this->bundlePrice);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected mixed $bundlePrice = null;
}
