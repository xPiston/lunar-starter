<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product photography for the demo catalogue, from Wikimedia Commons.
 *
 * The previous catalogue pinned one URL per photo by hand, which is the right
 * thing for seven products and impossible for three hundred and fifty. So this
 * searches instead — and searching is what makes the quality uneven, which is
 * what the scoring below is for.
 *
 * Commons rather than Openverse, which was the first choice: Openverse without
 * a registered client allows 20 requests a minute and **200 a day**, measured
 * from its own `x-ratelimit-limit-anon_sustained` header, and this catalogue
 * asks about ninety questions per full seed. Commons has no such wall, needs
 * no key and no registration, and its files are freely licensed. The price is
 * that its index is an encyclopaedia's and not a shop's: "safety goggles"
 * answers with a ski jumper, and "paint can" with a 32-pixel icon. Hence:
 *
 *  - A floor on the real dimensions, which removes the icons outright.
 *  - A keyword score on the title, so the file has to say it is the thing.
 *  - A penalty on titles that read like a scene rather than an object.
 *  - A blacklist of the kinds of file Commons has a great many of and a shop
 *    has none: logos, diagrams, maps, patents, stamps.
 *
 * Both layers are cached on disk: the answer to a search, and each downloaded
 * file. A second seed therefore touches the network zero times, which is what
 * keeps re-seeding cheap and the result identical — the same reason the rest
 * of the demo data is deterministic.
 */
trait FetchesCatalogPhotos
{
    /** Where the search answers and the files themselves are kept between runs. */
    private const CACHE_DISK = 'local';

    private const QUERY_CACHE = 'demo-catalog/queries';

    private const PHOTO_CACHE = 'demo-catalog/photos';

    /** Smaller than this is an icon, a thumbnail or a crop, never a photograph. */
    private const MIN_WIDTH = 700;

    private const MIN_HEIGHT = 500;

    /**
     * What Commons has plenty of and a shop has none of.
     *
     * The second half of this list was not guessed. It is what a first run
     * actually brought back: "screwdriver" returned the cocktail, "paint
     * brush" returned a butterfly called the Paint-brush Swift, and "ceramic
     * floor tile" returned a fourteenth-century archaeological find with a
     * FindID. An encyclopaedia indexes every sense of a word; a shop needs one.
     */
    private const UNWANTED = [
        'logo', 'icon', 'diagram', 'schematic', 'map ', 'chart', 'graph',
        'screenshot', 'stamp', 'coin', 'banknote', 'patent', 'drawing',
        'engraving', 'cartoon', 'comic', 'poster', 'plaque', 'signature',
        'coat of arms', 'flag of', 'gravestone', 'monument',
        'cocktail', 'drink', 'butterfly', 'moth', 'beetle', 'insect',
        'findid', 'archaeolog', 'medieval', 'century', 'excavat',
        'sculpture', 'painting of', 'mural', 'graffiti', 'tattoo',
        'dpla', 'lccn', 'burnt', 'usaf', 'navy', 'squadron', 'regiment',
        'aircraft', 'howitzer', 'vintage',
        // A second pass added these: Commons holds a great many scanned trade
        // catalogues and wartime industry photographs, and both match tool
        // vocabulary better than any product photograph does.
        'catalogue', 'catalog', 'merchandising', 'industries of war',
        'church', 'propeller',
    ];

    /**
     * A photograph of somebody using a thing is not a photograph of the thing.
     *
     * A penalty rather than a rejection, because a hand holding a tool is
     * often the clearest shot of its scale — but it has to lose to any plain
     * photograph of the object, which is what the size of the penalty buys.
     */
    private const SCENES = [
        'man ', 'woman ', 'boy ', 'girl ', 'child', 'kid', 'people', 'worker',
        'portrait', 'wedding', 'festival', 'parade', 'museum', 'exhibition',
        'motorcycle', 'motorbike', 'bicycle', 'soldier', 'military',
        'underwater', 'diving', 'diver', 'swimming', 'beach', 'snow',
    ];

    /**
     * Bumped whenever the rules above change, because the cache holds scored
     * results and not raw ones. Without it, tightening a filter would improve
     * nothing already looked up — which is exactly the case that matters,
     * since a filter is tightened because of something it let through.
     */
    private const FILTERS = 4;

    /**
     * The file for one product photo, downloaded once and kept.
     *
     * `$rank` is which of the scored candidates to take, so the two photos of
     * one product are two different files rather than the same one twice, and
     * two products sharing a photo family (every box of wood screws looks like
     * every other) still differ from each other.
     *
     * Returns null when nothing survived the filters, and the caller falls
     * back to a generated placeholder: seeding must never fail because
     * somebody else's website is having a bad day.
     */
    protected function catalogPhoto(string $query, int $rank, string $slug): ?string
    {
        $cached = self::PHOTO_CACHE.'/'.$slug.'-'.$rank.'.jpg';

        if (Storage::disk(self::CACHE_DISK)->exists($cached)) {
            return Storage::disk(self::CACHE_DISK)->path($cached);
        }

        $candidates = $this->photoCandidates($query);

        if ($candidates === []) {
            return null;
        }

        // Wrapped rather than indexed, because the rank is offset per product
        // sharing a query: every box of wood screws searches "wood screws",
        // and the second one takes the next photograph rather than the same.
        // Past the end it comes back round — two products sharing a picture is
        // a catalogue, two products that are the same row is a bug.
        $candidate = $candidates[$rank % count($candidates)];
        $body = $this->fetch($candidate['url']);

        if ($body === null || @imagecreatefromstring($body) === false) {
            return null;
        }

        Storage::disk(self::CACHE_DISK)->put($cached, $body);

        // The credit travels beside the file rather than in it: the seeder
        // reads it back to put the author and the licence on the media record,
        // and a cached photo must still know where it came from.
        Storage::disk(self::CACHE_DISK)->put(
            self::PHOTO_CACHE.'/'.$slug.'-'.$rank.'.json',
            (string) json_encode($candidate['credit'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return Storage::disk(self::CACHE_DISK)->path($cached);
    }

    /**
     * What the candidates for a query are called, best first.
     *
     * Downloads nothing. It exists because the titles are the cheap way to
     * judge a search: reading three hundred of them takes a minute and finds
     * the cocktail, the butterfly and the diver, where downloading three
     * hundred photographs to look at them takes an hour.
     *
     * @return array<int, array{title: string, score: int}>
     */
    protected function photoChoices(string $query, int $count = 2): array
    {
        return array_map(
            static fn (array $candidate): array => [
                'title' => $candidate['title'],
                'score' => $candidate['score'],
            ],
            array_slice($this->photoCandidates($query), 0, $count),
        );
    }

    /**
     * What a photo owes its source: author, licence, and the file's own page.
     *
     * @return array<string, string>
     */
    protected function photoCredit(string $slug, int $rank): array
    {
        $path = self::PHOTO_CACHE.'/'.$slug.'-'.$rank.'.json';

        if (! Storage::disk(self::CACHE_DISK)->exists($path)) {
            return [];
        }

        /** @var array<string, string> $credit */
        $credit = json_decode((string) Storage::disk(self::CACHE_DISK)->get($path), true) ?: [];

        return $credit;
    }

    /**
     * The candidates for one query, best first, with the search cached.
     *
     * @return array<int, array{url: string, title: string, score: int, credit: array<string, string>}>
     */
    private function photoCandidates(string $query): array
    {
        $cache = self::QUERY_CACHE.'/'.sha1(self::FILTERS.'|'.$query).'.json';

        if (Storage::disk(self::CACHE_DISK)->exists($cache)) {
            /** @var array<int, array{url: string, title: string, score: int, credit: array<string, string>}> $kept */
            $kept = json_decode((string) Storage::disk(self::CACHE_DISK)->get($cache), true) ?: [];

            return $kept;
        }

        [$wanted, $unwanted] = $this->terms($query);

        $url = 'https://commons.wikimedia.org/w/api.php?'.http_build_query([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'search',
            // Bitmaps only: Commons' SVG holdings are mostly diagrams, and a
            // shop's product shot is a photograph.
            'gsrsearch' => 'filetype:bitmap '.implode(' ', $wanted),
            'gsrlimit' => 24,
            'gsrnamespace' => 6,
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime|extmetadata',
            // A 1200-pixel rendition rather than the original: the originals
            // here run to twenty megabytes, and a storefront never shows one.
            'iiurlwidth' => 1200,
        ]);

        $body = $this->fetch($url);
        $decoded = $body === null ? null : json_decode($body, true);
        $pages = is_array($decoded) ? ($decoded['query']['pages'] ?? []) : [];

        $scored = [];

        foreach ($pages as $page) {
            $candidate = $this->score($wanted, $unwanted, $page);

            if ($candidate !== null) {
                $scored[] = $candidate;
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        Storage::disk(self::CACHE_DISK)->put(
            $cache,
            (string) json_encode($scored, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return $scored;
    }

    /**
     * Splits a query into what the title must look like and what disqualifies it.
     *
     * A word prefixed with `-` is a negative: "screwdriver tool -drink" is how
     * the catalogue says, once and in the place where the product is
     * described, that this particular word has another meaning behind a bar.
     * The negatives never go to the search engine — they are applied to the
     * titles that come back, which is the only place they are reliable.
     *
     * @return array{0: string[], 1: string[]}
     */
    private function terms(string $query): array
    {
        $wanted = [];
        $unwanted = [];

        foreach (preg_split('/\s+/', Str::lower(trim($query))) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            if (str_starts_with($word, '-')) {
                $unwanted[] = mb_substr($word, 1);

                continue;
            }

            $wanted[] = $word;
        }

        return [$wanted, $unwanted];
    }

    /**
     * Whether one search result can be a product photo, and how good a one.
     *
     * @param  string[]  $wanted
     * @param  string[]  $unwanted
     * @param  array<string, mixed>  $page
     * @return array{url: string, title: string, score: int, credit: array<string, string>}|null
     */
    private function score(array $wanted, array $unwanted, array $page): ?array
    {
        $info = $page['imageinfo'][0] ?? null;

        if (! is_array($info) || ! isset($info['thumburl'])) {
            return null;
        }

        if (! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        if (($info['width'] ?? 0) < self::MIN_WIDTH || ($info['height'] ?? 0) < self::MIN_HEIGHT) {
            return null;
        }

        $title = Str::lower((string) ($page['title'] ?? ''));

        foreach (array_merge(self::UNWANTED, $unwanted) as $reject) {
            if ($reject !== '' && str_contains($title, $reject)) {
                return null;
            }
        }

        $score = 0;

        // The file has to say it is the thing. Words of three letters or fewer
        // are skipped: "of", "kit" and "set" match everything.
        foreach ($wanted as $word) {
            if (mb_strlen($word) > 3 && str_contains($title, $word)) {
                $score += 3;
            }
        }

        if ($score === 0) {
            return null;
        }

        // A roughly square frame crops to a product tile without cutting the
        // object in half.
        $ratio = (int) $info['width'] / max(1, (int) $info['height']);

        if ($ratio >= 0.6 && $ratio <= 1.8) {
            $score += 2;
        }

        foreach (self::SCENES as $scene) {
            if (str_contains($title, $scene)) {
                $score -= 8;

                break;
            }
        }

        $meta = $info['extmetadata'] ?? [];

        return [
            'url' => (string) $info['thumburl'],
            'title' => (string) ($page['title'] ?? ''),
            'score' => $score,
            'credit' => [
                'title' => (string) ($page['title'] ?? ''),
                'author' => strip_tags((string) ($meta['Artist']['value'] ?? 'Wikimedia Commons')),
                'licence' => (string) ($meta['LicenseShortName']['value'] ?? 'see the file page'),
                'page' => (string) ($info['descriptionurl'] ?? ''),
            ],
        ];
    }

    /**
     * One HTTP request, politely.
     *
     * Wikimedia asks for a User-Agent that says who is calling and refuses the
     * default one; the pause is the courtesy owed to a free service being
     * asked ninety questions in a row.
     */
    private function fetch(string $url): ?string
    {
        usleep(400_000);

        $context = stream_context_create(['http' => [
            'timeout' => 20,
            'follow_location' => 1,
            'ignore_errors' => true,
            'header' => "User-Agent: lunar-starter-demo-seeder/1.0 (https://github.com/xPiston/lunar-starter)\r\n",
        ]]);

        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }
}
