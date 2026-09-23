<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Generated placeholder artwork for the demo content.
 *
 * Generated rather than downloaded: banners and article covers are the images
 * a real shop always replaces with its own, and a stock photo here would only
 * invite someone to ship it. They also need no network, and they come out
 * identical on every run, so the seeders stay idempotent.
 */
trait GeneratesPlaceholderImages
{
    /**
     * Writes a gradient banner carrying its own label to the public disk, and
     * returns the path to store on the record.
     *
     * @param  array{int[], int[]}  $colours  Top and bottom of the gradient.
     */
    protected function placeholderImage(
        string $label,
        string $directory,
        int $width = 1600,
        int $height = 640,
        array $colours = [[30, 64, 175], [15, 23, 42]],
    ): string {
        $image = imagecreatetruecolor($width, $height);

        // A vertical gradient between the two tones, drawn a row at a time.
        // Cheap, and it stops the placeholders looking like error pages.
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

        // Says what it is, in the image itself: nobody ships an image reading
        // "placeholder" by accident.
        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 60, (int) ($height / 2) - 20, $label, $white);
        imagestring($image, 3, 60, (int) ($height / 2) + 6, 'Placeholder - replace in the admin', $white);

        $path = $directory.'/'.str($label)->slug().'.png';

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $contents);

        return $path;
    }
}
