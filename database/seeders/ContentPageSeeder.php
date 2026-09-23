<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\ContentType;
use App\Models\ContentPage;
use Database\Seeders\Concerns\GeneratesPlaceholderImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo editorial content: one custom page, two published articles and one
 * draft.
 *
 * The draft is there on purpose - it is the only way the seeded data shows
 * that unpublished content exists in the back office and nowhere on the
 * storefront.
 */
final class ContentPageSeeder extends Seeder
{
    use GeneratesPlaceholderImages;

    private const DIRECTORY = 'content-pages';

    public function run(): void
    {
        // Same reasoning as HeroSlideSeeder: replacing the rows orphans the
        // images they pointed at.
        Storage::disk('public')->deleteDirectory(self::DIRECTORY);
        ContentPage::query()->delete();

        ContentPage::create([
            'type' => ContentType::Page,
            'title' => 'About us',
            'slug' => 'about-us',
            'excerpt' => 'Who we are and how this shop came about.',
            'body' => <<<'HTML'
                <p>We started out selling a handful of well-made basics, and that
                hasn't really changed - we just do it for more people now.</p>
                <h2>How we work</h2>
                <p>Small runs, materials we can explain, and prices that don't need
                a sale to look reasonable.</p>
                <ul>
                    <li>Every piece is made in limited quantities.</li>
                    <li>Orders ship within two working days.</li>
                    <li>Returns are free for thirty days.</li>
                </ul>
                <p>This page is demo content - replace it from the admin panel.</p>
                HTML,
            'published_at' => now()->subMonths(2),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'The autumn collection has landed',
            'slug' => 'autumn-collection',
            'excerpt' => 'Heavier fabrics, deeper colours, and the return of the shirt everyone asked about.',
            'image_path' => $this->placeholderImage(
                label: 'Autumn collection',
                directory: self::DIRECTORY,
                width: 1200,
                height: 800,
                colours: [[161, 98, 7], [69, 26, 3]],
            ),
            'body' => <<<'HTML'
                <p>The new season is in, and it leans warmer than last year: brushed
                cotton, a heavier flannel, and two colours we have been trying to get
                right for a while.</p>
                <h2>What's new</h2>
                <p>The check shirt is back in the original fit after a lot of emails
                about it. The tote now comes in the same leather as the backpack.</p>
                <blockquote>Made in small runs, so sizes go quickly.</blockquote>
                <p>This article is demo content - replace it from the admin panel.</p>
                HTML,
            'published_at' => now()->subDays(3),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'How we pick our materials',
            'slug' => 'how-we-pick-our-materials',
            'excerpt' => 'A short walk through what we look for before anything goes into production.',
            'image_path' => $this->placeholderImage(
                label: 'Our materials',
                directory: self::DIRECTORY,
                width: 1200,
                height: 800,
                colours: [[21, 128, 61], [12, 45, 30]],
            ),
            'body' => <<<'HTML'
                <p>Every fabric we use has to survive the same test: a month of being
                worn by whoever on the team argued for it.</p>
                <ol>
                    <li>It has to wash well at thirty degrees.</li>
                    <li>It has to keep its shape at the collar and cuffs.</li>
                    <li>We have to be able to name the mill it came from.</li>
                </ol>
                <p>This article is demo content - replace it from the admin panel.</p>
                HTML,
            'published_at' => now()->subWeeks(3),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'Behind the scenes: the winter shoot',
            'slug' => 'winter-shoot',
            'excerpt' => 'Not published yet - this one shows what a draft looks like.',
            'body' => '<p>Draft: visible in the admin panel, absent from the storefront until it is given a date.</p>',
            'published_at' => null,
        ]);
    }
}
