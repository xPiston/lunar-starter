<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSlideResource\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Models\HeroSlide;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Resources\BaseResource;

/**
 * Back office for the homepage slider.
 *
 * Extends Lunar's BaseResource rather than Filament's own so it inherits the
 * panel's staff permission model: without it, a resource added to the Lunar
 * panel would be visible to every staff member regardless of their role.
 *
 * This is the one place allowed to touch App\Models\HeroSlide directly. The
 * storefront never does - it goes through the HeroSlides port, which is why
 * the admin can change how slides are stored without the shop noticing.
 */
class HeroSlideResource extends BaseResource
{
    protected static ?string $model = HeroSlide::class;

    protected static ?string $permission = 'settings';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-gallery-horizontal';

    protected static ?int $navigationSort = 1;

    public static function getLabel(): string
    {
        return 'Homepage slide';
    }

    public static function getPluralLabel(): string
    {
        return 'Homepage slider';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Storefront';
    }

    /**
     * Enforces the `settings` permission on every action.
     *
     * BaseResource answers this by overriding `can()`, which Filament 3 used
     * for page access. Filament 4 routes `canViewAny()` through
     * `getAuthorizationResponse()` instead and never calls `can()`, so that
     * override now only hides the navigation item - the URL itself stays
     * reachable. Overriding the funnel every check goes through closes it.
     */
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return static::hasPermission()
            ? Response::allow()
            : Response::deny();
    }

    protected static function getMainFormComponents(): array
    {
        return [
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->autofocus(),

            TextInput::make('subtitle')
                ->helperText('Optional line under the title.')
                ->maxLength(255),

            // Stored on the `public` disk: these images are meant to be served
            // to anonymous visitors, so they go where `storage:link` exposes
            // them rather than on the private default disk.
            FileUpload::make('image_path')
                ->label('Image')
                ->required()
                ->image()
                ->disk('public')
                ->directory('hero-slides')
                ->imageEditor()
                // The slider crops to this ratio, so cropping here shows the
                // admin what visitors will actually see.
                ->imageCropAspectRatio('5:2')
                ->helperText('Wide image, around 1600x640. Anything else is cropped to fit.'),

            TextInput::make('link_url')
                ->label('Link')
                ->url()
                ->maxLength(255)
                ->helperText('Optional. Where the slide sends the visitor - a collection or product page.'),

            Toggle::make('is_visible')
                ->label('Visible')
                ->default(true)
                ->helperText('Hidden slides stay here but disappear from the homepage.'),

            TextInput::make('sort_order')
                ->label('Position')
                ->numeric()
                ->default(0)
                ->required()
                ->helperText('Low numbers first. The list can also be reordered by dragging.'),
        ];
    }

    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            // Dragging writes sort_order, the same column the storefront
            // orders by - so the admin arranges slides in the order visitors
            // will see them.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->height(48),

                TextColumn::make('title')
                    ->searchable(),

                TextColumn::make('subtitle')
                    ->limit(40)
                    ->toggleable(),

                // Editable from the list: hiding a slide is the one thing
                // someone will want to do in a hurry.
                ToggleColumn::make('is_visible')
                    ->label('Visible'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No slides yet')
            ->emptyStateDescription('Until a slide is added, the homepage shows its default heading.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroSlides::route('/'),
            'create' => CreateHeroSlide::route('/create'),
            'edit' => EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
