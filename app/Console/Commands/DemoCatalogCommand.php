<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ContentPageSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\ProductBundleSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Lunar\Models\CartLine;
use Lunar\Models\Collection;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Builds the demo catalogue, or part of it, on demand.
 *
 * `db:seed` runs the same seeder once when the template is installed and then
 * refuses to run again, which is right for an install and useless while the
 * catalogue is being written: a full seed is about seven hundred photo
 * downloads and as many media conversions, and nobody checks one description
 * by sitting through that.
 *
 *   ./bin/artisan demo:catalog --fresh --limit=3          # a taste, quickly
 *   ./bin/artisan demo:catalog --fresh --aisle=peinture   # one aisle, in full
 *   ./bin/artisan demo:catalog --fresh                    # the whole shop
 *
 * Photographs are cached on disk by the seeder, so the second run of any of
 * these touches the network for nothing it has already seen.
 *
 * `--fresh` empties the catalogue first and is therefore confirmable, like
 * `bench:catalog`: it is pointed at a development database, and saying so out
 * loud before deleting products is cheaper than the alternative.
 */
final class DemoCatalogCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:catalog
        {--fresh : Delete the existing catalogue first}
        {--limit= : At most this many products per aisle}
        {--photos=2 : Photographs per product}
        {--aisle=* : Only these aisles, by the slug in their file name}
        {--titles : Only report which photographs each product would get, writing nothing}
        {--force : Skip the confirmation}';

    protected $description = 'Seed the French DIY demo catalogue, in whole or in part';

    public function handle(): int
    {
        if ($this->option('titles')) {
            return $this->reportPhotoTitles();
        }

        if ($this->option('fresh')) {
            if (! $this->confirmToProceed()) {
                return self::FAILURE;
            }

            $this->wipe();
        }

        $seeder = app(DemoCatalogSeeder::class);
        $seeder->setCommand($this);

        $limit = $this->option('limit');
        $seeder->limitPerAisle = $limit === null ? null : (int) $limit;
        $seeder->photosPerProduct = (int) $this->option('photos');
        /** @var string[] $aisles */
        $aisles = $this->option('aisle');
        $seeder->onlyAisles = $aisles;

        $seeder->run();

        // Both of these are made of the catalogue rather than beside it, so
        // they are rebuilt with it: the kits are built from product variants
        // that have just been recreated with new ids, and the credits page
        // lists the photographs the shop is actually showing. Reseeding one
        // without the others leaves kits that add nothing to a basket and
        // attributions for pictures nobody can see.
        $bundles = app(ProductBundleSeeder::class);
        $bundles->setCommand($this);
        $bundles->run();

        app(ContentPageSeeder::class)->publishPhotoCredits();

        $this->newLine();
        $this->info(sprintf(
            '%d produits, %d variantes, %d collections, %d photos.',
            Product::count(),
            ProductVariant::count(),
            Collection::count(),
            Media::count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Prints what each product's search would bring back, and writes nothing.
     *
     * The quality of this catalogue's photography is decided by three hundred
     * search strings, and the only honest way to check one is to look at what
     * it returns. Titles are enough to catch the famous failures — Commons
     * answers "screwdriver" with a cocktail and "paint brush" with a butterfly
     * — and reading them costs a minute against an hour of downloads.
     *
     * The searches are cached as they go, so the seed that follows reuses
     * every answer this produced.
     */
    private function reportPhotoTitles(): int
    {
        $seeder = app(DemoCatalogSeeder::class);
        $seeder->setCommand($this);

        foreach ($seeder->catalogue() as $aisle) {
            $this->newLine();
            $this->info($aisle['name']);

            foreach ($aisle['products'] as $item) {
                $choices = $seeder->choicesFor($item['photo']);

                if ($choices === []) {
                    $this->warn(sprintf('  %-52s %-34s AUCUNE', $item['name'], $item['photo']));

                    continue;
                }

                $this->line(sprintf(
                    '  %-52s %-34s %s',
                    mb_strimwidth($item['name'], 0, 50, '…'),
                    $item['photo'],
                    implode('  |  ', array_map(
                        static fn (array $choice): string => str_replace('File:', '', $choice['title']),
                        $choices,
                    )),
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Empties the catalogue.
     *
     * Longer than it looks like it should be, for two reasons that only show
     * up once: Lunar's Product soft-deletes, and nothing in its schema
     * cascades — every foreign key is ON DELETE NO ACTION. A first attempt
     * deleted seven products and left behind fifteen variants, their prices,
     * their URLs and their media files, with no row pointing at any of it. So
     * each product is taken apart through its own relations and then
     * force-deleted, which is also why this walks `withTrashed()`: the rows
     * that first attempt soft-deleted are still there to clean up.
     *
     * Through Eloquent rather than TRUNCATE, because the media files on disk
     * belong to the rows: Spatie's observer removes the file when the record
     * goes, and a truncate would leave the whole library orphaned in storage.
     */
    private function wipe(): void
    {
        $this->warn('Suppression du catalogue existant…');

        /** @var EloquentCollection<int, Product> $products */
        $products = Product::withTrashed()->with('media')->get();

        foreach ($products as $product) {
            $product->media->each->delete();

            // Queried through the model rather than through $product->variants
            // so that withTrashed() applies: the relation does not carry it,
            // and a soft-deleted variant left behind is exactly the rubbish
            // this method exists to clear.
            /** @var EloquentCollection<int, ProductVariant> $variants */
            $variants = ProductVariant::withTrashed()->where('product_id', $product->id)->get();

            foreach ($variants as $variant) {
                // The carts come first, and they are the whole reason this
                // line exists: a cart line pointing at a deleted variant
                // cannot be priced, and it takes down every page for the
                // session holding it. LunarCartGateway now drops such a line
                // when it meets one, but leaving a hundred of them behind and
                // relying on that is leaving a trap.
                CartLine::query()
                    ->where('purchasable_type', $variant->getMorphClass())
                    ->where('purchasable_id', $variant->id)
                    ->delete();

                $variant->prices()->delete();
                $variant->values()->detach();
                $variant->forceDelete();
            }

            $product->collections()->detach();
            $product->productOptions()->detach();
            $product->customerGroups()->detach();
            $product->channels()->detach();
            $product->urls()->delete();
            $product->forceDelete();
        }

        // No withTrashed() and no forceDelete() here: Collection does not
        // soft-delete, unlike Product and ProductVariant. Which of Lunar's
        // models keep their deleted rows is not a pattern to assume — it is
        // per model, and asking for the wrong one throws.
        /** @var EloquentCollection<int, Collection> $collections */
        $collections = Collection::get();

        foreach ($collections as $collection) {
            $collection->customerGroups()->detach();
            $collection->channels()->detach();
            $collection->urls()->delete();
            $collection->delete();
        }

        /** @var EloquentCollection<int, ProductOption> $options */
        $options = ProductOption::get();

        foreach ($options as $option) {
            $option->values()->delete();
            $option->delete();
        }
    }
}
