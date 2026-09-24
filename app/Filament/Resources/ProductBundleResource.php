<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductBundleResource\Pages\CreateProductBundle;
use App\Filament\Resources\ProductBundleResource\Pages\EditProductBundle;
use App\Filament\Resources\ProductBundleResource\Pages\ListProductBundles;
use App\Models\ProductBundle;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Models\Currency;
use Lunar\Models\Product as LunarProduct;
use Lunar\Models\ProductVariant;

/**
 * Composing a bundle: what goes in it, and what it costs.
 *
 * The price is edited here rather than on Lunar's own price screens because
 * it is the whole point of a bundle - a set priced below the sum of its
 * parts. It is still stored in Lunar's polymorphic price table, so currency
 * and customer-group rules apply to it unchanged.
 *
 * See HeroSlideResource for why this extends Lunar's BaseResource and
 * overrides getAuthorizationResponse().
 */
class ProductBundleResource extends BaseResource
{
    protected static ?string $model = ProductBundle::class;

    protected static ?string $permission = 'catalogue';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-package-plus';

    protected static ?int $navigationSort = 4;

    public static function getLabel(): string
    {
        return 'Bundle';
    }

    public static function getPluralLabel(): string
    {
        return 'Product bundles';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Storefront';
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return static::hasPermission()
            ? Response::allow()
            : Response::deny();
    }

    protected static function getMainFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->autofocus()
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set): void {
                    // Create only: changing a name later must not move a URL
                    // customers already have.
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->rules(['alpha_dash']),

            Textarea::make('description')
                ->rows(3)
                ->maxLength(1000),

            FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->disk('public')
                ->directory('bundles')
                ->imageEditor(),

            TextInput::make('bundle_price')
                ->label('Bundle price')
                ->numeric()
                ->required()
                ->prefix(fn (): string => (string) Currency::getDefault()?->code)
                ->helperText('What the whole bundle costs. The storefront shows this against the sum of its parts, so it only reads as an offer if it is lower.'),

            Repeater::make('items')
                ->relationship()
                ->label('What is in it')
                ->minItems(1)
                ->schema([
                    Select::make('product_variant_id')
                        ->label('Product')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => ProductVariant::query()
                            ->with('product')
                            ->get()
                            ->mapWithKeys(function (ProductVariant $variant): array {
                                // Typed locally: Lunar resolves its models at
                                // runtime, so the relation is a bare Model as
                                // far as static analysis is concerned.
                                /** @var ?LunarProduct $product */
                                $product = $variant->product;

                                return [$variant->id => trim($product?->translateAttribute('name').' '.$variant->getOption())];
                            })
                            ->all()),

                    TextInput::make('quantity')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                ])
                ->columns(2)
                ->helperText('A bundle can only be sold as many times as its scarcest part allows.'),

            Toggle::make('is_active')
                ->label('On offer')
                ->default(true),
        ];
    }

    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                TextColumn::make('stock')
                    ->label('Can sell')
                    ->state(fn (ProductBundle $record): string => $record->getTotalInventory() === PHP_INT_MAX
                        ? 'Unlimited'
                        : (string) $record->getTotalInventory())
                    ->badge()
                    ->color(fn (ProductBundle $record): string => $record->getTotalInventory() > 0 ? 'success' : 'danger'),
                IconColumn::make('is_active')->label('On offer')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No bundles yet')
            ->emptyStateDescription('A bundle sells several products together, at a price of its own.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductBundles::route('/'),
            'create' => CreateProductBundle::route('/create'),
            'edit' => EditProductBundle::route('/{record}/edit'),
        ];
    }
}
