<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductReviewResource\Pages\ListProductReviews;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Models\Product;

/**
 * Moderation queue for customer reviews.
 *
 * Read-and-approve rather than edit: staff decide what becomes public, they
 * do not rewrite what a customer said. There is no create screen either - a
 * review comes from the storefront or it isn't one.
 *
 * See HeroSlideResource for why this extends Lunar's BaseResource and
 * overrides getAuthorizationResponse().
 */
class ProductReviewResource extends BaseResource
{
    protected static ?string $model = ProductReview::class;

    protected static ?string $permission = 'settings';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-star';

    protected static ?int $navigationSort = 3;

    public static function getLabel(): string
    {
        return 'Review';
    }

    public static function getPluralLabel(): string
    {
        return 'Product reviews';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Storefront';
    }

    /**
     * The number of reviews waiting, on the navigation item itself - the one
     * thing about this screen someone needs to know without opening it.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()->whereNull('approved_at')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        return static::hasPermission()
            ? Response::allow()
            : Response::deny();
    }

    protected static function getDefaultTable(Table $table): Table
    {
        return $table
            // Pending first: this is a queue, and the oldest unanswered
            // review is the one that has been waiting longest.
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('approved_at is not null'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('product_id')
                    ->label('Product')
                    // Resolved here rather than through a relation: the review
                    // table has no foreign key to Lunar's products, and a
                    // deleted product must not take its reviews' listing down.
                    ->formatStateUsing(static fn (int $state): string => Product::find($state)?->translateAttribute('name') ?? "#{$state} (deleted)"),

                TextColumn::make('rating')
                    ->formatStateUsing(static fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),

                IconColumn::make('verified_purchase')
                    ->label('Bought it')
                    ->boolean(),

                TextColumn::make('body')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('approved_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(static fn (mixed $state): string => $state === null ? 'Pending' : 'Published')
                    ->color(static fn (mixed $state): string => $state === null ? 'warning' : 'success'),
            ])
            ->filters([
                TernaryFilter::make('approved_at')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Published')
                    ->falseLabel('Pending')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('approved_at'),
                        false: fn (Builder $query) => $query->whereNull('approved_at'),
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Publish')
                    ->icon('lucide-check')
                    ->color('success')
                    ->visible(static fn (ProductReview $record): bool => $record->approved_at === null)
                    ->action(static fn (ProductReview $record) => $record->update(['approved_at' => now()])),

                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('lucide-eye-off')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(static fn (ProductReview $record): bool => $record->approved_at !== null)
                    ->action(static fn (ProductReview $record) => $record->update(['approved_at' => null])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Publish selected')
                        ->icon('lucide-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(static fn (Collection $records) => $records->each->update(['approved_at' => now()])),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Reviews written on the storefront land here for approval before anyone else sees them.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReviews::route('/'),
        ];
    }
}
