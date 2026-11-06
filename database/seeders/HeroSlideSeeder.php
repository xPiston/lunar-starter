<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HeroSlide;
use Database\Seeders\Concerns\GeneratesPlaceholderImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Three slides for the homepage slider, with generated placeholder banners -
 * see the trait for why they are generated rather than downloaded.
 *
 * Generated and not searched, unlike the product photographs: a banner is the
 * first thing a real shop replaces with its own artwork, and a stock photo
 * here would only invite somebody to ship it.
 */
final class HeroSlideSeeder extends Seeder
{
    use GeneratesPlaceholderImages;

    private const DIRECTORY = 'hero-slides';

    /**
     * @var array<int, array{title: string, subtitle: string, colours: array{int[], int[]}}>
     */
    private const SLIDES = [
        [
            'title' => 'Tout pour attaquer le chantier',
            'subtitle' => 'Outillage, quincaillerie et matériaux, en stock et prêts à partir.',
            'colours' => [[180, 83, 9], [69, 26, 3]],
        ],
        [
            'title' => 'La peinture, de la sous-couche à la finition',
            'subtitle' => 'Teintes, rouleaux, enduits : une pièce repeinte en un week-end.',
            'colours' => [[30, 64, 175], [15, 23, 42]],
        ],
        [
            'title' => 'Le jardin se prépare maintenant',
            'subtitle' => 'Outils à main, arrosage et terreau pour la saison qui vient.',
            'colours' => [[21, 128, 61], [12, 45, 30]],
        ],
    ];

    public function run(): void
    {
        // Wiping the directory keeps re-runs from piling up orphaned files -
        // the rows are replaced, so the images they pointed at are dead.
        Storage::disk('public')->deleteDirectory(self::DIRECTORY);
        HeroSlide::query()->delete();

        foreach (self::SLIDES as $position => $slide) {
            HeroSlide::create([
                'title' => $slide['title'],
                'subtitle' => $slide['subtitle'],
                'image_path' => $this->placeholderImage(
                    label: $slide['title'],
                    directory: self::DIRECTORY,
                    colours: $slide['colours'],
                ),
                'is_visible' => true,
                'sort_order' => $position,
            ]);
        }
    }
}
