<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\ContentType;
use App\Models\ContentPage;
use Database\Seeders\Concerns\GeneratesPlaceholderImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Demo editorial content: two custom pages, two published articles and one
 * draft.
 *
 * The draft is there on purpose - it is the only way the seeded data shows
 * that unpublished content exists in the back office and nowhere on the
 * storefront.
 *
 * The second page is the photo credits, and it is not decoration: the product
 * photography comes from Wikimedia Commons under Creative Commons and
 * public-domain licences, most of which require naming the author. A demo
 * that quietly strips that is a demo that teaches the wrong habit, so the
 * credits are generated from the media records themselves and are therefore
 * always exactly what the catalogue is actually showing.
 */
final class ContentPageSeeder extends Seeder
{
    use GeneratesPlaceholderImages;

    private const DIRECTORY = 'content-pages';

    public const CREDITS_SLUG = 'credits-photos';

    public function run(): void
    {
        // Same reasoning as HeroSlideSeeder: replacing the rows orphans the
        // images they pointed at.
        Storage::disk('public')->deleteDirectory(self::DIRECTORY);
        ContentPage::query()->delete();

        ContentPage::create([
            'type' => ContentType::Page,
            'title' => 'Qui sommes-nous',
            'slug' => 'qui-sommes-nous',
            'excerpt' => 'Une quincaillerie de quartier devenue magasin de bricolage, et ce qui n\'a pas changé depuis.',
            'body' => <<<'HTML'
                <p>On a commencé avec un rayon de visserie et deux clients par jour, et
                au fond le métier n'a pas bougé : savoir ce qu'on vend et le dire
                franchement, y compris quand la réponse est « ce n'est pas ce qu'il
                vous faut ».</p>
                <h2>Ce à quoi on tient</h2>
                <ul>
                    <li>Des conseils donnés par des gens qui ont fait le chantier dont ils parlent.</li>
                    <li>Des fiches produit qui disent les dimensions, les normes et les limites.</li>
                    <li>Des pièces détachées, parce qu'un outil se répare.</li>
                </ul>
                <p>Cette page est du contenu de démonstration : remplacez-la depuis
                l'administration.</p>
                HTML,
            'published_at' => now()->subMonths(2),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'Choisir sa cheville : le guide court',
            'slug' => 'choisir-sa-cheville',
            'excerpt' => 'Mur plein, cloison creuse, charge lourde : trois situations, trois chevilles, et la plus fréquente des erreurs.',
            'image_path' => $this->placeholderImage(
                label: 'Choisir sa cheville',
                directory: self::DIRECTORY,
                width: 1200,
                height: 800,
                colours: [[180, 83, 9], [69, 26, 3]],
            ),
            'body' => <<<'HTML'
                <p>La question n'est jamais « quelle cheville » mais « quel mur ».
                Percez trois centimètres : si la poussière est grise et fine, c'est du
                béton ; blanche et légère, du plâtre ; rouge, de la brique.</p>
                <h2>Les trois cas</h2>
                <ol>
                    <li><strong>Mur plein</strong> — cheville nylon à expansion. Diamètre 6 pour une
                    tringle, 8 dès qu'il y a du poids.</li>
                    <li><strong>Cloison en plaque de plâtre</strong> — cheville métallique à
                    expansion. La cheville nylon, elle, arrache le carton et laisse un trou de
                    deux centimètres.</li>
                    <li><strong>Charge lourde ou parpaing creux</strong> — scellement chimique, avec
                    son tamis. C'est la seule solution qui tienne un garde-corps.</li>
                </ol>
                <p>L'erreur la plus courante tient en une phrase : un trou trop grand ne se
                rattrape pas avec une cheville plus grosse, il se rattrape en rebouchant et en
                reperçant à côté.</p>
                <p>Cet article est du contenu de démonstration : remplacez-le depuis
                l'administration.</p>
                HTML,
            'published_at' => now()->subDays(4),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'Repeindre une pièce : l\'ordre des opérations',
            'slug' => 'repeindre-une-piece',
            'excerpt' => 'Ce qui fait un mur net tient moins à la peinture qu\'à ce qu\'on a fait avant de l\'ouvrir.',
            'image_path' => $this->placeholderImage(
                label: 'Repeindre une piece',
                directory: self::DIRECTORY,
                width: 1200,
                height: 800,
                colours: [[30, 64, 175], [15, 23, 42]],
            ),
            'body' => <<<'HTML'
                <p>Une pièce repeinte en un week-end, c'est une journée de préparation et
                trois heures de peinture. Dans l'autre sens, ça prend deux week-ends.</p>
                <ol>
                    <li>Reboucher, poncer, dépoussiérer. Un trou rebouché la veille se ponce
                    sans se creuser.</li>
                    <li>Sous-couche sur le plâtre neuf et sur les reprises d'enduit, sinon la
                    finition boira de façon inégale et les reprises se verront.</li>
                    <li>Masquer la veille au soir, pas le matin même : le ruban adhère mieux
                    sur une surface froide et sèche.</li>
                    <li>Plafond, puis murs, puis boiseries. Jamais l'inverse.</li>
                    <li>Retirer le ruban quand la peinture est sèche au toucher mais pas dure —
                    trop tôt elle coule, trop tard elle s'arrache.</li>
                </ol>
                <blockquote>Deux couches croisées valent mieux qu'une couche épaisse : la
                peinture ne couvre pas par l'épaisseur, elle couvre par le nombre de
                passages.</blockquote>
                <p>Cet article est du contenu de démonstration : remplacez-le depuis
                l'administration.</p>
                HTML,
            'published_at' => now()->subWeeks(3),
        ]);

        ContentPage::create([
            'type' => ContentType::Post,
            'title' => 'Poser une terrasse en bois : le dossier',
            'slug' => 'poser-une-terrasse',
            'excerpt' => 'Pas encore publié — celui-ci montre à quoi ressemble un brouillon.',
            'body' => '<p>Brouillon : visible dans l\'administration, absent de la vitrine tant qu\'il n\'a pas de date.</p>',
            'published_at' => null,
        ]);

        $this->publishPhotoCredits();
    }

    /**
     * Publishes the photo credits, read from the media library itself.
     *
     * Public, because `demo:catalog` reseeds the catalogue on its own and the
     * credits have to follow: a page listing photographs the shop no longer
     * shows is worse than no page.
     *
     * Grouped by author rather than listed per product. Three hundred
     * products carry six hundred photographs and a good number share a
     * photographer; what a licence asks for is that the author be named where
     * the work is used, not that the list be as long as possible.
     */
    public function publishPhotoCredits(): void
    {
        $credits = Media::query()
            ->where('collection_name', 'images')
            ->get()
            ->filter(static fn (Media $media): bool => ($media->getCustomProperty('page') ?? '') !== '')
            ->groupBy(static fn (Media $media): string => (string) $media->getCustomProperty('author'))
            ->sortKeys();

        if ($credits->isEmpty()) {
            return;
        }

        $rows = $credits->map(static function ($group, string $author): string {
            $works = $group
                ->unique(static fn (Media $media): string => (string) $media->getCustomProperty('page'))
                ->map(static function (Media $media): string {
                    $title = str_replace('File:', '', (string) $media->getCustomProperty('title'));

                    return sprintf(
                        '<a href="%s" rel="noopener nofollow">%s</a> <em>(%s)</em>',
                        e((string) $media->getCustomProperty('page')),
                        e($title),
                        e((string) $media->getCustomProperty('licence')),
                    );
                })
                ->implode(', ');

            return '<li><strong>'.e($author).'</strong> — '.$works.'</li>';
        })->implode('');

        ContentPage::updateOrCreate(
            ['slug' => self::CREDITS_SLUG],
            [
                'type' => ContentType::Page,
                'title' => 'Crédits photographiques',
                'excerpt' => 'D\'où viennent les photographies du catalogue, et sous quelle licence.',
                'body' => <<<HTML
                    <p>Les photographies de ce catalogue de démonstration proviennent de
                    <a href="https://commons.wikimedia.org" rel="noopener nofollow">Wikimedia Commons</a>,
                    sous licences Creative Commons ou dans le domaine public. Elles ne
                    représentent pas les articles réellement vendus : ce sont des visuels de
                    démonstration, et une boutique réelle les remplace par ses propres photos
                    depuis l'administration.</p>
                    <p>La licence de chaque fichier est indiquée entre parenthèses, et son
                    nom renvoie à sa page d'origine, où figurent le texte complet de la
                    licence et l'historique du fichier.</p>
                    <ul>{$rows}</ul>
                    HTML,
                'published_at' => now(),
            ],
        );
    }
}
