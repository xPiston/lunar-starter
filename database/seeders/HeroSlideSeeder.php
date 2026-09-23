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
            'title' => 'New season, new arrivals',
            'subtitle' => 'Fresh pieces added every week.',
            'colours' => [[30, 64, 175], [15, 23, 42]],
        ],
        [
            'title' => 'Everyday essentials',
            'subtitle' => 'The basics, done properly.',
            'colours' => [[21, 128, 61], [12, 45, 30]],
        ],
        [
            'title' => 'Carry it all',
            'subtitle' => 'Bags and accessories built to last.',
            'colours' => [[161, 98, 7], [69, 26, 3]],
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
