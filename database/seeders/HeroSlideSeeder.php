<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HeroSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Three slides for the homepage slider, with generated placeholder banners.
 *
 * Deliberately generated rather than downloaded: a hero banner is the one
 * image a shop always replaces with its own artwork, and a stock photo here
 * would only invite someone to ship it. These are obviously placeholders,
 * they need no network, and they're re-made from scratch on every run so the
 * seeder stays idempotent.
 */
final class HeroSlideSeeder extends Seeder
{
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
                'image_path' => $this->generateBanner($slide['title'], $slide['colours']),
                'is_visible' => true,
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * @param  array{int[], int[]}  $colours
     */
    private function generateBanner(string $title, array $colours): string
    {
        $width = 1600;
        $height = 640;

        $image = imagecreatetruecolor($width, $height);

        // A vertical gradient between the two tones, drawn a row at a time.
        // Cheap, and it stops the banners looking like error pages.
        [[$r1, $g1, $b1], [$r2, $g2, $b2]] = $colours;

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $colour = imagecolorallocate(
                $image,
                (int) ($r1 + ($r2 - $r1) * $ratio),
                (int) ($g1 + ($g2 - $g1) * $ratio),
                (int) ($b1 + ($b2 - $b1) * $ratio),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $colour);
        }

        // Says what it is, in the image itself: nobody ships a banner reading
        // "placeholder" by accident.
        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 60, (int) ($height / 2) - 20, $title, $white);
        imagestring($image, 3, 60, (int) ($height / 2) + 6, 'Placeholder - replace in the admin', $white);

        $path = self::DIRECTORY.'/'.str($title)->slug().'.png';

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $contents);

        return $path;
    }
}
