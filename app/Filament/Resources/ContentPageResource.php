<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Content\ContentType;
use App\Filament\Resources\ContentPageResource\Pages\CreateContentPage;
use App\Filament\Resources\ContentPageResource\Pages\EditContentPage;
use App\Filament\Resources\ContentPageResource\Pages\ListContentPages;
use App\Models\ContentPage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Resources\BaseResource;

/**
 * Back office for custom pages and articles.
 *
 * See HeroSlideResource for why this extends Lunar's BaseResource and why it
 * overrides getAuthorizationResponse() rather than relying on can().
 */
class ContentPageResource extends BaseResource
{
    protected static ?string $model = ContentPage::class;

    protected static ?string $permission = 'settings';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-newspaper';

    protected static ?int $navigationSort = 2;

    public static function getLabel(): string
    {
        return 'Page';
    }

    public static function getPluralLabel(): string
    {
        return 'Pages & news';
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
            Select::make('type')
                ->options([
                    ContentType::Page->value => 'Page (standalone, linked in the footer)',
                    ContentType::Post->value => 'Article (listed under News)',
                ])
                ->default(ContentType::Page->value)
                ->required()
                ->native(false),

            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->autofocus()
                // Only fills the slug while creating: changing a title later
                // must not silently move a published URL and break every link
                // to it.
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set): void {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->rules(['alpha_dash'])
                ->helperText('The URL this is served at. Changing it breaks existing links.'),

            Textarea::make('excerpt')
                ->rows(2)
                ->maxLength(255)
                ->helperText('Shown on the news list and used as the description for search engines and link previews. Falls back to the start of the body.'),

            FileUpload::make('image_path')
                ->label('Cover image')
                ->image()
                ->disk('public')
                ->directory('content-pages')
                ->imageEditor(),

            RichEditor::make('body')
                ->required()
                // Attachments go to the same public disk as the cover, so a
                // dragged-in image is served like any other storefront asset.
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory('content-pages/attachments'),

            DateTimePicker::make('published_at')
                ->label('Published at')
                ->seconds(false)
                ->helperText('Empty keeps it a draft. A future date publishes it automatically when that time comes.'),
        ];
    }

    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(static fn (ContentType $state): string => $state === ContentType::Post ? 'Article' : 'Page'),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),

                // Draft / Scheduled / the date: the three states published_at
                // encodes, spelled out rather than left as an empty cell.
                TextColumn::make('published_at')
                    ->label('Status')
                    ->formatStateUsing(static fn (?Carbon $state): string => match (true) {
                        $state === null => 'Draft',
                        $state->isFuture() => 'Scheduled for '.$state->toFormattedDateString(),
                        default => $state->toFormattedDateString(),
                    })
                    ->badge()
                    ->color(static fn (?Carbon $state): string => match (true) {
                        $state === null => 'gray',
                        $state->isFuture() => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        ContentType::Page->value => 'Pages',
                        ContentType::Post->value => 'Articles',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nothing published yet')
            ->emptyStateDescription('Create a page for the footer, or an article to appear under News.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'create' => CreateContentPage::route('/create'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
